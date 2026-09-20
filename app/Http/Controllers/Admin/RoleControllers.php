<?php


namespace App\Http\Controllers\Admin;


use App\Http\Constants\CodeMessageConstants;
use App\Http\Controllers\Controller;
use App\Http\Model\Role;
use App\Http\Model\User;
use Illuminate\Support\Facades\DB;
use App\Http\Services\PermissionService;
use Illuminate\Http\Request;

class RoleControllers extends Controller
{
    public function __construct(Request $request)
    {
        $this->request = $request;
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
        $role = Role::where("guard_name", "admin");
        if ($this->request->input('name')) {
            $role = $role->where('name', 'like', "%" . $this->request->input('name') . "%");
        }
        return $role->orderBy('sort', 'desc')->paginate($pageSize, ['*'], "page", $page);
    }

    /**
     * FunctionName：detail
     * Description：详情
     * Author：cherish
     * @return mixed
     */
    public function detail()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Role())->getTable() . ',id'],
        ]);
        return Role::find($this->request->input('id'));
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
            'name' => ['required', 'unique:' . (new Role())->getTable() . ',name'],
        ]);
        return Role::create(['name' => $this->request->input('name'), 'guard_name' => 'admin']);
    }

    /**
     * FunctionName：update
     * Description：更新
     * Author：cherish
     * @return mixed
     */
    public function update()
    {
        $id = $this->request->input('id');
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Role())->getTable() . ',id'],
            'name' => ['required', 'unique:' . (new Role())->getTable() . ',name,' . $id],
        ]);
        return DB::transaction(function () use ($id) {
            $this->checkRole($id);
            return Role::where('id', $id)->update(['name' => $this->request->input('name')]);
        });

    }

    /**
     * FunctionName：delete
     * Description：删除
     * Author：cherish
     * @return mixed
     */
    public function delete()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Role())->getTable() . ',id'],
        ]);
        $id = $this->request->input('id');
        return DB::transaction(function () use ($id) {
            $this->checkRole($id);
            return Role::destroy($id);
        });
    }


    /**
     * FunctionName：permission
     * Description：获取角色权限
     * Author：cherish
     * @return PermissionService
     */
    public function permission()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Role())->getTable() . ',id'],
        ]);
        $role = Role::query()->findOrFail($this->request->input('id'));
        return new PermissionService($role->permissions);
    }

    /**
     * FunctionName：addPermission
     * Description：添加权限
     * Author：cherish
     */
    public function addPermission()
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new Role())->getTable() . ',id'],
            'permissions' => 'array'
        ]);
        $id = $this->request->input('id');
        return \DB::transaction(function () use ($id) {
            //  $this->checkRole($id);
            $role = Role::query()->findOrFail($id);
            $role->syncPermissions($this->request->input('permissions', []));
            return;
        });
    }

    /**
     * FunctionName：checkRole
     * Description：检查是否操作系统管理员
     * Author：cherish
     * @param $id
     */
    private function checkRole($id)
    {
        $role = Role::find($id);
        if ($role['alias']) {
            throw \ExceptionFactory::business(CodeMessageConstants::IS_ADMIN_CHECK);
        }
        if ($role['name'] == "admin")
            throw \ExceptionFactory::business(CodeMessageConstants::IS_ADMIN);
        return;
    }

    /**
     * FunctionName：userList
     * Description：获取角色下面的用户
     * Author：cherish
     * @return mixed
     */
    public function userList()
    {
        $this->request->validate([
            'alias' => ['required', 'exists:' . (new Role())->getTable() . ',alias'],
        ]);
        $role = Role::where('alias', $this->request->input('alias'))->first();
        return User::role($role['name'])->get();
    }
}
