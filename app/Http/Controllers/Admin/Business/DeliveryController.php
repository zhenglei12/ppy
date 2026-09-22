<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\DeliveryEvidence;
use App\Http\Model\DeliveryMilestone;
use App\Http\Model\DeliveryProject;
use App\Http\Model\DeliveryTask;
use App\Http\Model\Order;
use App\Http\Services\OrderAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function __construct(private OrderAccessService $access)
    {
    }

    public function dashboard()
    {
        $projectIds = $this->visibleProjectIds();
        $global = $this->hasAnyRole(['admin', 'technical_director', 'sales_director', 'finance']);

        return [
            'pending_assign_count' => $global ? Order::where('current_stage', 'tech_assign')->count() : 0,
            'active_project_count' => DeliveryProject::whereIn('id', $projectIds)->where('status', 'active')->count(),
            'completed_project_count' => DeliveryProject::whereIn('id', $projectIds)->where('status', 'completed')->count(),
            'overdue_task_count' => DeliveryTask::whereIn('project_id', $projectIds)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
            'risk_distribution' => DeliveryProject::whereIn('id', $projectIds)->select('health_status', DB::raw('count(*) as total'))->groupBy('health_status')->pluck('total', 'health_status'),
            'node_distribution' => DeliveryProject::whereIn('id', $projectIds)->select('current_node', DB::raw('count(*) as total'))->groupBy('current_node')->pluck('total', 'current_node'),
            'task_status_distribution' => DeliveryTask::whereIn('project_id', $projectIds)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'workload' => DeliveryTask::whereIn('project_id', $projectIds)->select('assignee_id', DB::raw('count(*) as total'))->whereNotIn('status', ['completed', 'cancelled'])->groupBy('assignee_id')->orderByDesc('total')->limit(20)->get(),
        ];
    }

    public function projects(Request $request)
    {
        $query = DeliveryProject::with(['order:id,order_no,customer_id,customer_legal_name,current_stage', 'order.customer:id,legal_name,brand_name'])->withCount(['tasks', 'milestones']);
        $this->applyProjectScope($query);

        return $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('health_status'), fn ($q) => $q->where('health_status', $request->input('health_status')))
            ->when($request->filled('optimizer_id'), fn ($q) => $q->where('optimizer_id', $request->integer('optimizer_id')))
            ->latest('id')->paginate($request->integer('pageSize', 20));
    }

    public function projectDetail(Request $request)
    {
        $project = DeliveryProject::with(['order.customer.contacts', 'milestones', 'tasks.milestone', 'tasks.evidences', 'evidences'])->findOrFail($request->integer('id'));
        $this->authorizeProject($project);

        return $project;
    }

    public function createProject(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:order,id', 'unique:delivery_projects,order_id'],
            'technical_director_id' => ['nullable', 'exists:users,id'], 'optimizer_id' => ['nullable', 'exists:users,id'],
            'planned_start_date' => ['nullable', 'date'], 'planned_end_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'],
            'success_criteria' => ['nullable', 'string'], 'baseline_data' => ['nullable', 'array'], 'key_keywords' => ['nullable', 'array'],
            'risk_summary' => ['nullable', 'string'], 'renewal_warning_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($data) {
            $data['project_no'] = $this->number('PRJ');
            $data['created_by'] = Auth::id();
            $project = DeliveryProject::create($data);
            $this->createDefaultMilestones($project);

            return $project->load('order.customer', 'milestones');
        });
    }

    public function updateProject(Request $request)
    {
        $project = DeliveryProject::findOrFail($request->integer('id'));
        $this->authorizeProject($project);
        $data = $request->validate([
            'technical_director_id' => ['sometimes', 'nullable', 'exists:users,id'], 'optimizer_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'planned_start_date' => ['sometimes', 'nullable', 'date'], 'actual_start_date' => ['sometimes', 'nullable', 'date'], 'planned_end_date' => ['sometimes', 'nullable', 'date'], 'actual_end_date' => ['sometimes', 'nullable', 'date'],
            'current_node' => ['sometimes', Rule::in(['D1', 'D3', 'D7', 'D15', 'D30', 'D60', 'D90'])], 'status' => ['sometimes', Rule::in(['pending', 'active', 'paused', 'completed', 'terminated'])],
            'health_status' => ['sometimes', Rule::in(['green', 'yellow', 'red'])], 'success_criteria' => ['sometimes', 'nullable', 'string'],
            'baseline_data' => ['sometimes', 'nullable', 'array'], 'key_keywords' => ['sometimes', 'nullable', 'array'], 'risk_summary' => ['sometimes', 'nullable', 'string'], 'renewal_warning_at' => ['sometimes', 'nullable', 'date'],
        ]);
        if (array_intersect(array_keys($data), ['technical_director_id', 'optimizer_id'])) {
            abort_unless($this->hasAnyRole(['admin', 'technical_director']), 403, '仅技术总监可以调整项目成员');
        }
        $project->update($data);
        if (array_key_exists('optimizer_id', $data) || array_key_exists('technical_director_id', $data)) {
            $project->order()->update(collect($data)->only(['optimizer_id', 'technical_director_id'])->all());
        }

        return $project->fresh()->load('order.customer', 'milestones');
    }

    public function updateMilestone(Request $request)
    {
        $milestone = DeliveryMilestone::findOrFail($request->integer('id'));
        $this->authorizeProject($milestone->project);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'processing', 'reviewing', 'completed', 'overdue'])], 'planned_at' => ['sometimes', 'nullable', 'date'],
            'acceptance_criteria' => ['sometimes', 'nullable', 'string'], 'review_result' => ['sometimes', 'nullable', 'string'],
            'owner_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);
        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
            $data['reviewed_by'] = Auth::id();
        }
        $milestone->update($data);
        if (($data['status'] ?? null) === 'completed') {
            $milestone->project()->update(['current_node' => $milestone->milestone_code]);
        }

        return $milestone->fresh();
    }

    public function tasks(Request $request)
    {
        $query = DeliveryTask::with(['project.order.customer:id,legal_name,brand_name', 'milestone:id,milestone_code,milestone_name']);
        $query->whereIn('project_id', $this->visibleProjectIds());

        return $query->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->integer('assignee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now()))
            ->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at')->paginate($request->integer('pageSize', 20));
    }

    public function saveTask(Request $request)
    {
        $id = $request->integer('id') ?: null;
        $required = $id ? 'sometimes' : 'required';
        $data = $request->validate([
            'id' => ['nullable', 'exists:delivery_tasks,id'], 'project_id' => [$required, 'exists:delivery_projects,id'], 'milestone_id' => ['nullable', 'exists:delivery_milestones,id'],
            'task_type' => [$required, Rule::in(['material', 'content', 'publish', 'review', 'meeting', 'other'])], 'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'task_standard' => ['nullable', 'string'], 'template_name' => ['nullable', 'max:255'],
            'assignee_id' => [$required, 'exists:users,id'], 'reviewer_id' => ['nullable', 'exists:users,id'], 'priority' => ['sometimes', 'integer', 'between:1,4'], 'due_at' => ['nullable', 'date'],
        ]);
        unset($data['id']);
        if ($id) {
            $task = DeliveryTask::findOrFail($id);
            $this->authorizeProject($task->project);
            $task->update($data);
        } else {
            $project = DeliveryProject::findOrFail($data['project_id']);
            $this->authorizeProject($project);
            if (! empty($data['milestone_id'])) {
                abort_unless(DeliveryMilestone::whereKey($data['milestone_id'])->where('project_id', $project->id)->exists(), 422, '里程碑不属于该项目');
            }
            $data['task_no'] = $this->number('TASK');
            $data['created_by'] = Auth::id();
            $task = DeliveryTask::create($data);
        }

        return $task->fresh()->load('project.order.customer', 'milestone');
    }

    public function taskStatus(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'exists:delivery_tasks,id'], 'status' => ['required', Rule::in(['pending', 'processing', 'reviewing', 'completed', 'returned', 'cancelled'])], 'return_reason' => ['nullable', 'required_if:status,returned', 'max:1000'], 'result_url' => ['nullable', 'max:1000']]);
        $task = DeliveryTask::findOrFail($data['id']);
        $this->authorizeProject($task->project);
        $values = ['status' => $data['status'], 'result_url' => $data['result_url'] ?? $task->result_url];
        if ($data['status'] === 'completed') {
            $values['completed_at'] = now();
        }
        if ($data['status'] === 'returned') {
            $values['return_reason'] = $data['return_reason'];
            $values['revision_count'] = $task->revision_count + 1;
        }
        $task->update($values);

        return $task->fresh()->load('evidences');
    }

    public function submitEvidence(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:delivery_projects,id'], 'task_id' => ['nullable', 'exists:delivery_tasks,id'],
            'evidence_type' => ['required', Rule::in(['link', 'image', 'file', 'report', 'record'])], 'title' => ['required', 'max:255'],
            'file_url' => ['required', 'max:1000'], 'version_no' => ['nullable', 'max:32'], 'description' => ['nullable', 'string'],
        ]);
        $project = DeliveryProject::findOrFail($data['project_id']);
        $this->authorizeProject($project);
        if (! empty($data['task_id'])) {
            abort_unless(DeliveryTask::whereKey($data['task_id'])->where('project_id', $project->id)->exists(), 422, '任务不属于该项目');
        }
        $data['submitted_by'] = Auth::id();
        $data['submitted_at'] = now();

        return DeliveryEvidence::create($data)->load('task');
    }

    public function reviewEvidence(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'exists:delivery_evidences,id'], 'status' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'required_if:status,rejected', 'max:500']]);
        $evidence = DeliveryEvidence::findOrFail($data['id']);
        $this->authorizeProject($evidence->project);
        $evidence->update(['status' => $data['status'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return $evidence->fresh();
    }

    private function createDefaultMilestones(DeliveryProject $project): void
    {
        $items = [['D1', '成功标准确认', 1], ['D3', '成功标准完成', 2], ['D7', '事实与基线', 3], ['D15', '方向确认', 4], ['D30', '价值复盘', 5], ['D60', '续费预警', 6], ['D90', '报价与到账', 7]];
        $start = $project->planned_start_date ? $project->planned_start_date->copy() : now();
        foreach ($items as [$code, $name, $sequence]) {
            DeliveryMilestone::create(['project_id' => $project->id, 'milestone_code' => $code, 'milestone_name' => $name, 'sequence_no' => $sequence, 'planned_at' => $start->copy()->addDays((int) substr($code, 1))]);
        }
    }

    private function applyProjectScope($query): void
    {
        $user = Auth::user();
        $roles = $user->roles->pluck('alias')->all();
        if (array_intersect($roles, ['admin', 'sales_director', 'finance'])) {
            return;
        }
        if (in_array('technical_director', $roles, true)) {
            $visibleUserIds = $this->access->visibleDeliveryUserIds();
            $query->where(function ($scope) use ($user, $visibleUserIds) {
                $scope->where('technical_director_id', $user->id);
                if ($visibleUserIds) {
                    $scope->orWhereIn('optimizer_id', $visibleUserIds);
                }
            });

            return;
        }
        $query->where(fn ($q) => $q->where('optimizer_id', $user->id)->orWhere('technical_director_id', $user->id)->orWhereHas('order', fn ($o) => $o->where('sales_user_id', $user->id)));
    }

    private function visibleProjectIds()
    {
        $query = DeliveryProject::query();
        $this->applyProjectScope($query);

        return $query->select('id');
    }

    private function hasAnyRole(array $roles): bool
    {
        return (bool) array_intersect(Auth::user()->roles->pluck('alias')->all(), $roles);
    }

    private function authorizeProject(DeliveryProject $project): void
    {
        $query = DeliveryProject::whereKey($project->id);
        $this->applyProjectScope($query);
        abort_unless($query->exists(), 403, '无权访问该交付项目');
    }

    private function number(string $prefix): string
    {
        return $prefix.date('YmdHis').Str::upper(Str::random(4));
    }
}
