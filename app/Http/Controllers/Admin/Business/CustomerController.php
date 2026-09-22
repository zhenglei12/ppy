<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->where('source', 'local')->with(['owner:id,name,employee_no', 'coOwner:id,name,employee_no'])
            ->withCount(['contacts', 'orders']);

        $this->applyDataScope($query);

        $query->when($request->filled('keyword'), function ($query) use ($request) {
            $keyword = $request->input('keyword');
            $query->where(function ($query) use ($keyword) {
                $query->where('customer_no', 'like', "%{$keyword}%")
                    ->orWhere('legal_name', 'like', "%{$keyword}%")
                    ->orWhere('brand_name', 'like', "%{$keyword}%")
                    ->orWhere('credit_code', 'like', "%{$keyword}%");
            });
        })->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('customer_level'), fn ($q) => $q->where('customer_level', $request->input('customer_level')))
            ->when($request->filled('owner_user_id'), fn ($q) => $q->where('owner_user_id', $request->integer('owner_user_id')))
            ->when($request->filled('next_follow_start'), fn ($q) => $q->where('next_follow_at', '>=', $request->input('next_follow_start')))
            ->when($request->filled('next_follow_end'), fn ($q) => $q->where('next_follow_at', '<=', $request->input('next_follow_end')));

        return $query->latest('id')->paginate($request->integer('pageSize', 20));
    }

    public function show(Request $request)
    {
        $customer = Customer::where('source', 'local')->with(['contacts', 'owner:id,name,employee_no', 'coOwner:id,name,employee_no', 'orders:id,order_no,customer_id,customer_legal_name,payable_amount,paid_amount,receivable_amount,current_stage,business_status,health_status,next_action_at'])
            ->findOrFail($request->integer('id'));
        $this->authorizeCustomer($customer);

        return $customer;
    }

    public function store(Request $request)
    {
        $data = $this->validateCustomer($request);
        $contacts = $request->input('contacts', []);
        unset($data['contacts']);

        return DB::transaction(function () use ($data, $contacts) {
            $data['customer_no'] = $data['customer_no'] ?? $this->number('CUS');
            $data['owner_user_id'] = $data['owner_user_id'] ?? Auth::id();
            $customer = Customer::create($data);
            $this->saveContacts($customer, $contacts);

            return $customer->load(['contacts', 'owner:id,name,employee_no', 'coOwner:id,name,employee_no']);
        });
    }

    public function update(Request $request)
    {
        $customer = Customer::findOrFail($request->integer('id'));
        $this->authorizeCustomer($customer);
        $data = $this->validateCustomer($request, $customer->id, true);
        unset($data['contacts']);

        return DB::transaction(function () use ($request, $customer, $data) {
            $customer->update($data);
            if ($request->has('contacts')) {
                $this->saveContacts($customer, $request->input('contacts', []));
            }

            return $customer->fresh()->load(['contacts', 'owner:id,name,employee_no', 'coOwner:id,name,employee_no']);
        });
    }

    public function destroy(Request $request)
    {
        $customer = Customer::findOrFail($request->integer('id'));
        $this->authorizeCustomer($customer);
        abort_if($customer->orders()->exists(), 422, '该客户已有订单，不能删除');
        $customer->delete();

        return ['deleted' => true];
    }

    private function validateCustomer(Request $request, ?int $id = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'customer_no' => ['sometimes', 'nullable', 'string', 'max:64', Rule::unique('crm_customers', 'customer_no')->ignore($id)],
            'legal_name' => [$required, 'string', 'max:255'],
            'credit_code' => ['nullable', 'string', 'max:32', Rule::unique('crm_customers', 'credit_code')->ignore($id)],
            'brand_name' => ['nullable', 'string', 'max:128'],
            'industry' => ['nullable', 'string', 'max:128'],
            'province' => ['nullable', 'string', 'max:64'],
            'city' => ['nullable', 'string', 'max:64'],
            'district' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:64'],
            'customer_level' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'owner_user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'co_owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'attribution_reason' => ['nullable', 'string', 'max:255'],
            'contact_authorized' => ['sometimes', 'boolean'],
            'material_authorized' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['lead', 'opportunity', 'customer', 'in_service', 'lost'])],
            'next_follow_at' => ['nullable', 'date'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.id' => ['sometimes', 'integer'],
            'contacts.*.contact_name' => ['required_with:contacts', 'string', 'max:64'],
            'contacts.*.mobile' => ['nullable', 'string', 'max:32'],
            'contacts.*.position_name' => ['nullable', 'string', 'max:128'],
            'contacts.*.wechat_no' => ['nullable', 'string', 'max:64'],
            'contacts.*.email' => ['nullable', 'email', 'max:128'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
        ]);
    }

    private function saveContacts(Customer $customer, array $contacts): void
    {
        $keptIds = [];
        foreach ($contacts as $index => $item) {
            $payload = collect($item)->only(['contact_name', 'mobile', 'position_name', 'wechat_no', 'email', 'is_primary'])->all();
            $payload['is_primary'] = (bool) ($item['is_primary'] ?? $index === 0);
            if (! empty($item['id'])) {
                $contact = $customer->contacts()->findOrFail($item['id']);
                $contact->update($payload);
            } else {
                $contact = $customer->contacts()->create($payload);
            }
            $keptIds[] = $contact->id;
        }
        $customer->contacts()->whereNotIn('id', $keptIds)->delete();
    }

    private function applyDataScope($query): void
    {
        $user = Auth::user();
        $roles = $user->roles->pluck('alias')->all();
        if (array_intersect($roles, ['admin', 'sales_director', 'finance', 'technical_director'])) {
            return;
        }
        if (in_array('sales_manager', $roles, true)) {
            $teamIds = \App\Http\Model\User::where('direct_manager_id', $user->id)->pluck('id')->push($user->id);
            $query->whereIn('owner_user_id', $teamIds);

            return;
        }
        $query->where(fn ($q) => $q->where('owner_user_id', $user->id)->orWhere('co_owner_user_id', $user->id));
    }

    private function authorizeCustomer(Customer $customer): void
    {
        $query = Customer::whereKey($customer->id);
        $this->applyDataScope($query);
        abort_unless($query->exists(), 403, '无权访问该客户');
    }

    private function number(string $prefix): string
    {
        return $prefix.date('YmdHis').Str::upper(Str::random(4));
    }
}
