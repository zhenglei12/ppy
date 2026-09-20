<?php

namespace App\Http\Controllers\Admin;

use App\Http\Constants\CodeMessageConstants;
use App\Http\Controllers\Controller;
use App\Http\Model\Department;
use App\Http\Model\User;
use App\Http\Services\UserServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserControllers extends Controller
{
    public function __construct(Request $request, UserServices $services)
    {
        $this->request = $request;
        $this->services = $services;
    }

    /**
     * FunctionName：list
     * Description：列表
     * Author：cherish
     *
     * @return mixed
     */
    public function list()
    {
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 10;
        $user = new User();
        if ($this->request->input('username')) {
            $user = $user->where('name', 'like', '%' . $this->request->input('username') . '%');
        }
        $user = $user->with('department')->paginate($pageSize, ['*'], 'page', $page);
        if ($user->items()) {
            foreach ($user->items() as $values) {
                $us = User::findOrFail($values['id']);
                $values['roles'] = $us->roles;
            }
        }

        return $user;
    }

    /**
     * 人员状态统计。
     *
     * 在职人数严格按 employment_status = active 统计；
     * 本月入职人数按 hire_date 落在当前自然月统计。
     */
    public function statistics()
    {
        $query = User::query();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'active_count' => (clone $query)->where('employment_status', 'active')->count(),
            'month_hire_count' => (clone $query)->whereBetween('hire_date', [$monthStart, $monthEnd])->count(),
            'probation_count' => (clone $query)->where('employment_status', 'probation')->count(),
            'resigned_count' => (clone $query)->where('employment_status', 'resigned')->count(),
            'transferred_count' => (clone $query)->where('employment_status', 'transferred')->count(),
            'statistics_month' => now()->format('Y-m'),
        ];
    }

    /**
     * FunctionName：personalDetail
     * Description：用户详情
     * Author：cherish
     *
     * @return mixed
     */
    public function personalDetail()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new User())->getTable() . ',id'],
        ]);

        return User::find($this->request->input('id'));
    }

    /**
     * FunctionName：add
     * Description：创建
     * Author：cherish
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function add()
    {
        $this->request->validate([
            'username' => ['required', 'unique:' . (new User())->getTable() . ',name'],
            'password' => 'required',
            'department_id' => ['required', 'exists:' . (new Department())->getTable() . ',id'],
            'employee_no' => ['nullable', 'string', 'max:64', 'unique:' . (new User())->getTable() . ',employee_no'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'position_name' => ['nullable', 'string', 'max:100'],
            'direct_manager_id' => ['nullable', 'exists:' . (new User())->getTable() . ',id'],
            'employment_status' => ['nullable', 'in:probation,active,transferred,resigned'],
            'hire_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'regular_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:64'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'certificate_images' => ['nullable', 'array'],
            'certificate_images.*' => ['required', 'string', 'max:1000'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_plan_version' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
        ]);
        $data = $this->employeeData();
        $data = array_merge($data, [
            'name' => $this->request->input('username'),
            'password' => Hash::make($this->request->input('password')),
            'department_id' => $this->request->input('department_id'),
        ]);

        return User::create($data);
    }

    /**
     * FunctionName：update
     * Description：更新
     * Author：cherish
     *
     * @return mixed
     */
    public function update()
    {
        $id = $this->request->input('id');
        $this->request->validate([
            'id' => ['required', 'exists:' . (new User())->getTable() . ',id'],
            'username' => ['required', 'unique:' . (new User())->getTable() . ',name,' . $id],
            'department_id' => ['required', 'exists:' . (new Department())->getTable() . ',id'],
            'employee_no' => ['nullable', 'string', 'max:64', 'unique:' . (new User())->getTable() . ',employee_no,' . $id],
            'mobile' => ['nullable', 'string', 'max:32'],
            'position_name' => ['nullable', 'string', 'max:100'],
            'direct_manager_id' => ['nullable', 'exists:' . (new User())->getTable() . ',id', 'not_in:' . $id],
            'employment_status' => ['nullable', 'in:probation,active,transferred,resigned'],
            'hire_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'regular_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:64'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'certificate_images' => ['nullable', 'array'],
            'certificate_images.*' => ['required', 'string', 'max:1000'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_plan_version' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
        ]);
        $data = $this->employeeData();
        $data['department_id'] = $this->request->input('department_id');
        $data['name'] = $this->request->input('username');
        if ($this->request->input('password')) {
            $data['password'] = Hash::make($this->request->input('password'));
        }
        $user = User::find($this->request->input('id'));
        if ($user['admin']) {
            throw \ExceptionFactory::business(CodeMessageConstants::IS_ADMIN);
        }

        return User::where('id', $this->request->input('id'))->update($data);
    }

    /**
     * 获取用户档案扩展字段；未传入的字段不会覆盖原值。
     */
    private function employeeData(): array
    {
        return $this->request->only([
            'employee_no',
            'mobile',
            'position_name',
            'direct_manager_id',
            'employment_status',
            'hire_date',
            'probation_end_date',
            'regular_date',
            'resign_date',
            'emergency_contact_name',
            'emergency_contact_phone',
            'contract_start_date',
            'contract_end_date',
            'certificate_images',
            'base_salary',
            'salary_plan_version',
            'status',
        ]);
    }

    /**
     * FunctionName：delete
     * Description：删除
     * Author：cherish
     *
     * @return mixed
     */
    public function delete()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new User())->getTable() . ',id'],
        ]);
        $user = User::find($this->request->input('id'));
        if ($user['admin']) {
            throw \ExceptionFactory::business(CodeMessageConstants::IS_ADMIN);
        }

        return User::where('id', $this->request->input('id'))->delete();
    }

    /**
     * FunctionName：roleList
     * Description：用户所属
     * Author：cherish
     *
     * @return mixed
     */
    public function roleList()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new User())->getTable() . ',id'],
        ]);
        $user = User::findOrFail($this->request->input('id'));

        return $user->roles;
    }

    /**
     * FunctionName：addRole
     * Description：添加角色
     * Author：cherish
     */
    public function addRole()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new User())->getTable() . ',id'],
        ]);

        $user = User::findOrFail($this->request->input('id'));
        $user->syncRoles($this->request->input('roles', []));
    }

    /**
     * FunctionName：login
     * Description：授权
     * Author：cherish
     */
    public function login()
    {
        $this->request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        return $this->services->login($this->request->input('username'), $this->request->input('password'));
    }

    /**
     * FunctionName：detail
     * Description：获取用户详情
     * Author：cherish
     *
     * @return mixed
     */
    public function detail()
    {
        $user = \Auth::user();
        $user->roles;

        return $user;
    }

    /**
     * FunctionName：permission
     * Description：获取登陆用户的权限
     * Author：cherish
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function permission()
    {
        return \Auth::user()->getAllPermissions();
    }

    /**
     * FunctionName：logout
     * Description：退出登陆
     * Author：cherish
     *
     * @return mixed
     */
    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();
    }
}
