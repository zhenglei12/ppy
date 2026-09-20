<?php


namespace App\Http\Controllers\Admin;


use App\Http\Constants\CodeMessageConstants;
use App\Http\Controllers\Controller;
use App\Http\Model\Classify;
use App\Http\Model\Department;
use App\Http\Model\Order;
use App\Http\Model\OrderLogs;
use App\Http\Model\User;
use App\Http\Services\ExportsOrderService;
use App\Http\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OrderControllers extends Controller
{
    public function __construct(Request $request, OrderService $services)
    {
        $this->request = $request;
        $this->services = $services;
    }


    /**
     * FunctionName：getInitSelect
     * Description：根据角色和部门获取权限
     * User: cherish
     * @param $order
     * @return mixed|void
     */
    public function getInitSelect($order)
    {
        $user = \Auth::user();
        $department_id = $user->department_id;
        //查询当前部门信息
        $department = Department::where('id', $department_id)->first();
        //查询用户当前角色
        $role = $user->roles->pluck('alias')->toArray();
        if (in_array('admin', $role)) {
            return $order;
        } elseif (in_array('after_admin', $role)) {
            $order = $order->whereNotNull('after_name');
            if ($department) {
                if ($department['level'] == 3) {
                    $userName = User::where('department_id', $department['id'])->pluck('name');
                    $parentDepartment = Department::where("id", $department['parent_id'])->first();
                    if ($userName->count()) {
                        if ($parentDepartment['alias'] == "staff")
                            $order = $order->whereIn('staff_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "edit")
                            $order = $order->whereIn('edit_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "after")
                            $order = $order->whereIn('after_name', $userName->toArray());
                    } else {
                        $order = $order->whereNull('staff_name');
                    }
                }
            }
        } elseif (in_array('staff_admin', $role)) {
            $order = $order->whereNotNull('staff_name');
            if ($department) {
                if ($department['level'] == 3) {
                    $userName = User::where('department_id', $department['id'])->pluck('name');
                    $parentDepartment = Department::where("id", $department['parent_id'])->first();
                    if ($userName->count()) {
                        if ($parentDepartment['alias'] == "staff")
                            $order = $order->whereIn('staff_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "edit")
                            $order = $order->whereIn('edit_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "after")
                            $order = $order->whereIn('after_name', $userName->toArray());
                    } else {
                        $order = $order->whereNull('staff_name');
                    }
                }
            }
        } elseif (in_array('edit_admin', $role)) {
            // $order = $order->whereNotNull('edit_name');
            if ($department) {
                if ($department['level'] == 3) {
                    $userName = User::where('department_id', $department['id'])->pluck('name');
                    $parentDepartment = Department::where("id", $department['parent_id'])->first();
                    if ($userName->count()) {
                        if ($parentDepartment['alias'] == "staff")
                            $order = $order->whereIn('staff_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "edit")
                            $order = $order->whereIn('edit_name', $userName->toArray());
                        if ($parentDepartment['alias'] == "after")
                            $order = $order->whereIn('after_name', $userName->toArray());
                    } else {
                        $order = $order->whereNull('staff_name');
                    }
                }
            }
        } elseif (!in_array('after_admin', $role) && in_array('after', $role)) {
            $order = $order->where('after_name', $user->name);
        } elseif (!in_array('edit_admin', $role) && in_array('edit', $role)) {
            $order = $order->where('edit_name', $user->name);
        } elseif (!in_array('staff_admin', $role) && in_array('staff', $role)) {
            $order = $order->where('staff_name', $user->name);
        }
        return $order;
    }

    /**
     * FunctionName：list
     * Description：列表
     * Author：cherish
     * @return mixed
     */
    public function list()
    {
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 10;
        $order = new Order();
        $order = $this->getInitSelect($order);
        if ($this->request->input('subject')) {
            $order = $order->where('subject', 'like', "%" . $this->request->input('subject') . "%");
        }
        if ($this->request->input('word_number')) {
            $order = $order->where('word_number', $this->request->input('word_number'));
        }
        if ($this->request->input('task_type')) {
            $order = $order->where('task_type', '=', $this->request->input('task_type'));
        }
        if ($this->request->input('id')) {
            $order = $order->where('id', '=', $this->request->input('id'));
        }
        if ($this->request->input('major_name')) {
            $order = $order->where('major_name', 'like', "%" . $this->request->input('major_name') . "%");
        }
        if ($this->request->input('classify_id')) {
            $order = $order->where('classify_id', 'like', "%" . $this->request->input('classify_id') . "%");
        }
        if ($this->request->input('name')) {
            $order = $order->where('name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('staff_name')) {
            $order = $order->where('staff_name', 'like', "%" . $this->request->input('staff_name') . "%");
        }
        if ($this->request->input('edit_name')) {
            $order = $order->where('edit_name', 'like', "%" . $this->request->input('edit_name') . "%");
        }
        if ($this->request->input('status')) {
            $order = $order->where('status', '=', $this->request->input('status'));
        }

        if ($this->request->filled('amount')) {
            $order = $order->where('amount', '>=', $this->request->input('amount'));
        }
        if ($this->request->filled('amount_end')) {
            $order = $order->where('amount', '<=', $this->request->input('amount_end'));
        }

        $order = $this->applySubmissionTimeFilter($order);
        //        if ($this->request->input('submission_time')) {
        //            $order = $order->where('submission_time', '<=', $this->request->input('submission_time'));
        //        }
        //        if ($this->request->input('created_at')) {
        //            $order = $order->where('created_at', 'like', "%" . $this->request->input('created_at') . "%");
        //        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        return $order->orderBy('created_at', 'desc')->with('classify')->paginate($pageSize, ['*'], "page", $page);
    }

    /**
     * FunctionName：personalDetail
     * Description：用户详情
     * Author：cherish
     * @return mixed
     */
    public function detail()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
        ]);
        return Order::find($this->request->input('id'));
    }

    /**
     * FunctionName：delete
     * Description：删除
     * Author：cherish
     * @return bool|null
     * @throws \Exception
     */
    public function delete()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
        ]);
        return Order::where('id', $this->request->input('id'))->delete();
    }

    /**
     * FunctionName：add
     * Description：创建
     * Author：cherish
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function add()
    {
        $this->request->validate([
            'subject' => ['required'],
            'word_number' => 'required',
            'task_type' => 'required',
            'task_ask' => 'required',
            'name' => 'required',
            'submission_time' => 'required',
        ]);
        $data = $this->request->input();
        $data['staff_name'] = Auth::user()->name;
        if (isset($data['classify_id'])) {
            $data['classify_local_id'] = (new ManuscriptBankControllers())->getClassifyId($data['classify_id']);
            $data['classify_id'] = implode(",", $data['classify_id']);
        } else {
            $data['classify_local_id'] = null;
            $data['classify_id'] = null;
        }
        if (isset($data['amount']) && isset($data['received_amount'])) {
            if ($data['amount'] == $data['received_amount'])
                $data['finance_check'] = 1;
        }
        return Order::create($data);
    }

    /**
     * FunctionName：add
     * Description：更新
     * Author：cherish
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function update()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'subject' => ['required'],
            'word_number' => 'required',
            'task_type' => 'required',
            'task_ask' => 'required',
            'name' => 'required',
            'submission_time' => 'required',
        ]);
        $data = self::initData($this->request->input());
        if (isset($data['classify_id'])) {
            $data['classify_local_id'] = (new ManuscriptBankControllers())->getClassifyId($data['classify_id']);
            $data['classify_id'] = implode(",", $data['classify_id']);
        } else {
            $data['classify_local_id'] = null;
            $data['classify_id'] = null;
        }
        if (isset($data['amount']) && isset($data['received_amount'])) {
            if ($data['amount'] == $data['received_amount'])
                $data['finance_check'] = 1;
        }
        return Order::where('id', $this->request->input('id'))->Update($data);
    }

    /**
     * FunctionName：after
     * Description：售后
     * Author：cherish
     * @return mixed
     */
    public function after()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'after_banlace' => ['required'],
            //            'after_reason' => ['required'],
            //            'after_img' => ['required'],
        ]);
        $data = $this->request->input();
        $data['after_time'] = date("Y-m-d H:i:s");
        $data['othen_amount'] = $this->request->input('after_banlace');
        return Order::where('id', $this->request->input('id'))->Update($data);
    }

    /**
     * FunctionName：statistics
     * Description：统计
     * Author：cherish
     * @return mixed
     */
    public function statistics()
    {
        $order = new Order();
        $user = \Auth::user();
        if ($user->roles->pluck('alias')[0] == 'staff') {
            $order = $order->where('staff_name', $user['name']);
        }

        $year = date('Y');
        $start_time = $year . '-01-01 00:00:00';
        $end_time = $year . '-12-31 23:59:59';

        $data['amount_count'] = $order->whereDate('created_at', '>=', $start_time)->whereDate('created_at', '<=', $end_time)->sum('amount');
        $data['received_amount_count'] = $order->whereDate('created_at', '>=', $start_time)->whereDate('created_at', '<=', $end_time)->sum('received_amount');
        $data['after_banlace_count'] = $order->whereDate('created_at', '>=', $start_time)->whereDate('created_at', '<=', $end_time)->sum('othen_amount');
        $data['month_amount_count'] = $order->whereDate('created_at', '<=', date('Y-m-t'))->whereDate('created_at', '>=', date('Y-m-01'))->sum('amount');
        // $data['month_amount_count'] = $order->whereBetween('created_at', [date('Y-m-01'), date('Y-m-t')])->sum('amount');
        // $data['month_received_amount_count'] = $order->whereBetween('created_at', [date('Y-m-01'), date('Y-m-t')])->sum('received_amount');
        $data['month_received_amount_count'] = $order->whereDate('created_at', '<=', date('Y-m-t'))->whereDate('created_at', '>=', date('Y-m-01'))->sum('received_amount');
        $data['month_after_banlace_count'] = $order->whereDate('created_at', '<=', date('Y-m-t'))->whereDate('created_at', '>=', date('Y-m-01'))->sum('othen_amount');
        return $data;
    }

    /**
     * FunctionName：count_num
     * Description：统计字数
     * Author：cherish
     * @return mixed
     */
    public function count_num()
    {
        $order = new Order();
        $user = \Auth::user();
        if ($user->roles->pluck('alias')[0] == 'edit') {
            $order = $order->where('edit_name', $user['name']);
        }
        $data['count_num'] = $order->sum('word_number');
        return $data;
    }

    public function manuscript_score()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'manuscript_score' => ['required'],
        ]);
        return Order::where('id', $this->request->input('id'))->update(['manuscript_score' => $this->request->input('manuscript_score')]);
    }

    /**
     * FunctionName：status
     * Description：修改状态
     * Author：cherish
     * @return mixed
     */
    public function status()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'status' => ['required'],
        ]);
        $data = ['status' => $this->request->input('status')];
        $data['proposal'] = $this->request->input('status');
        $order = Order::find($this->request->input('id'));
        $orderLogs = [];
        $orderLogs['order_id'] = $this->request->input('id');
        $orderLogs['remark'] = $this->statusReplace(\Auth::user()->name, $order['status'], $this->request['status']);
        $orderLogs['url'] = $order['manuscript'];
        if ($this->request->input('manuscript') && $this->request->input('status') == 2) {
            $data['manuscript'] = $this->request->input('manuscript');
            $orderLogs['url'] = $this->request->input('manuscript');
        }

        if ($this->request->input('reason')) {
            $orderLogs['reason'] = $this->request->input('reason');
        }
        if ($this->request->input('submission_time')) {
            $data['submission_time'] = $this->request->input('submission_time');
            $orderLogs['remark'] = $orderLogs['remark'] . ",将完成时间" . $order['submission_time'] . "修改为" . $this->request->input('submission_time');
        }
        return DB::transaction(function () use ($data, $orderLogs) {
            OrderLogs::create($orderLogs);
            return Order::where('id', $this->request->input('id'))->update($data);
        });
    }


    public function statusReplace($name, $historyStarus, $status)
    {
        $data = [
            '-1' => '等待安排',
            '1' => '写作进行',
            '2' => '打回修改',
            '3' => '订单完成',
            '4' => '提交客户',
            '5' => "已经交稿",
            '6' => "收款中"
        ];
        return $name . ",将订单状态" . $data[$historyStarus] . "修改为" . $data[$status];
    }

    /**
     * FunctionName：manuscript
     * Description：上传稿件
     * Author：cherish
     * @return mixed
     */
    public function manuscript()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'manuscript' => ['required'],
            "alter_word" => ['required'],
            "classify_id" => ['required']
        ]);
        $order = Order::find($this->request->input('id'));
        $alter_word = $this->request->input('alter_word') ?? $order['alter_word'];
        $orderLogs['remark'] = $this->statusReplace(\Auth::user()->name, $order['status'], 5);
        $orderLogs['url'] = $this->request->input('manuscript');
        $orderLogs['order_id'] = $this->request->input('id');
        $data = $this->request->input();
        if (isset($data['classify_id'])) {
            $classify_local_id = (new ManuscriptBankControllers())->getClassifyId($this->request->input('classify_id'));
            $classify_id = implode(",", $this->request->input('classify_id'));
        } else {
            $classify_local_id = null;
            $classify_id = null;
        }
        return DB::transaction(function () use ($orderLogs, $alter_word, $classify_id, $classify_local_id) {
            OrderLogs::create($orderLogs);
            return Order::where('id', $this->request->input('id'))->Update(['manuscript' => $this->request->input('manuscript'), "status" => 5, "proposal" => 5, 'alter_word' => $alter_word, 'classify_id' => $classify_id, 'classify_local_id' => $classify_local_id, 'edit_submit_time' => date("Y-m-d H:i:s")]);
        });
    }

    public function logs()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
        ]);
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 10;
        $orderLogs = new OrderLogs();
        $orderLogs = $orderLogs->where('order_id', "=", $this->request->input('id'));
        return $orderLogs->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], "page", $page);
    }

    /**
     * FunctionName：editName
     * Description：分配编辑
     * Author：cherish
     * @return mixed
     */
    public function editName()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'edit_name' => ['required'],
        ]);
        return Order::where('id', $this->request->input('id'))->Update(['edit_name' => $this->request->input('edit_name'), "status" => 1]);
    }

    /**
     * FunctionName：grade
     * Description：更新难度等级
     * Author：cherish
     * @return mixed
     */
    public function grade()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Order())->getTable() . ',id'],
            'hard_grade' => ['required'],
        ]);
        return Order::where('id', $this->request->input('id'))->Update(['hard_grade' => $this->request->input('hard_grade')]);
    }


    /**
     * FunctionName：initData
     * Description：初始化数据
     * Author：cherish
     * @param $data
     * @return array
     */
    public function initData($data)
    {
        $initData = [
            'subject' => $data['subject'],
            'word_number' => $data['word_number'],
            'task_type' => $data['task_type'],
            'task_ask' => $data['task_ask'],
            'name' => $data['name'],
            'submission_time' => $data['submission_time'],
            'phone' => $data['phone'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'received_amount' => $data['received_amount'] ?? 0,
            'pay_img' => $data['pay_img'] ?? '',
            'receipt_account' => $data['receipt_account'] ?? '',
            'remark' => $data['remark'] ? $data['remark'] : '',
            'shop_name' => $data['shop_name'] ?? '',
            'education' => $data['education'] ?? 4,
            'major_name' => $data['major_name'] ?? '',
            'duplicate_checking' => $data['duplicate_checking'] ?? '',
            'attachment' => $data['attachment'] ?? '',
            'othen_amount' => $data['othen_amount'] ?? null,
            'receipt_account_new' => $data['receipt_account_new'] ?? '',
            'time_rank' => $data['time_rank'] ? $data['time_rank'] : 2,
        ];
        return $initData;
    }


    public function export()
    {
        $order = new Order();
        $order = $this->getInitSelect($order);
        if ($this->request->input('subject')) {
            $order = $order->where('subject', 'like', "%" . $this->request->input('subject') . "%");
        }
        if ($this->request->input('word_number')) {
            $order = $order->where('word_number', $this->request->input('word_number'));
        }
        if ($this->request->input('task_type')) {
            $order = $order->where('task_type', '=', $this->request->input('task_type'));
        }
        if ($this->request->input('id')) {
            $order = $order->where('id', '=', $this->request->input('id'));
        }
        if ($this->request->input('name')) {
            $order = $order->where('name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('type')) {
            $order = $order->where('type', 'like', "%" . $this->request->input('type') . "%");
        }
        if ($this->request->input('classify_id')) {
            $order = $order->where('classify_id', 'like', "%" . $this->request->input('classify_id') . "%");
        }
        if ($this->request->input('staff_name')) {
            $order = $order->where('staff_name', 'like', "%" . $this->request->input('staff_name') . "%");
        }
        if ($this->request->input('edit_name')) {
            $order = $order->where('edit_name', 'like', "%" . $this->request->input('edit_name') . "%");
        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        $order = $this->applySubmissionTimeFilter($order);

        //        if ($this->request->input('submission_time')) {
        //            $order = $order->where('submission_time', '<=', $this->request->input('submission_time'));
        //        }
        if ($this->request->input('status')) {
            $order = $order->where('status', '=', $this->request->input('status'));
        }
        if ($this->request->input('is_audit')) {
            $order = $order->where('is_audit', '=', $this->request->input('is_audit'));
        }
        //        if ($this->request->input('created_at')) {
        //            $order = $order->where('created_at', 'like', "%" . $this->request->input('created_at') . "%");
        //        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        $data = $order->get();
        //  Log::debug("11", [ count($data)]);
        if (count($data) < 1)
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_ORDER_NULL);
        if (count($data) > 2000)
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_ORDER_NUM);
        $filename = '订单列表.xls';
        return Excel::download(new ExportsOrderService($data), $filename);
    }

    /**
     * 根据当前角色应用订单截止日期筛选。
     *
     * 客服排除已完成和已提交客户的订单；编辑排除已完成和已交稿的订单。
     * 其他角色不受该筛选条件影响。
     *
     * @param mixed $order
     * @return mixed
     */
    private function applySubmissionTimeFilter($order)
    {
        if (! $this->request->filled('submission_time')) {
            return $order;
        }

        $roles = Auth::user()->roles->pluck('alias')->toArray();

        if (in_array('staff', $roles, true)) {
            return $order
                ->whereDate('submission_time', '<=', $this->request->input('submission_time'))
                ->whereNotIn('status', [3, 4]);
        }

        if (in_array('edit', $roles, true)) {
            return $order
                ->whereDate('submission_time', '<=', $this->request->input('submission_time'))
                ->whereNotIn('status', [3, 5]);
        }

        return $order;
    }
}
