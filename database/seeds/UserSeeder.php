<?php

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    private $permission = [
        ["name" => "user-list", "guard_name" => "admin", "alias" => "用户列表"],
        ["name" => "user-personal.detail", "guard_name" => "admin", "alias" => "用户详情"],
        ["name" => "user-update", "guard_name" => "admin", "alias" => "用户更新"],
        ["name" => "user-add", "guard_name" => "admin", "alias" => "用户添加"],
        ["name" => "user-delete", "guard_name" => "admin", "alias" => "用户删除"],
        ["name" => "user-role.list", "guard_name" => "admin", "alias" => "用户角色"],
        ["name" => "user-add.role", "guard_name" => "admin", "alias" => "用户角色更新"],

        ["name" => "role-list", "guard_name" => "admin", "alias" => "角色列表"],
        ["name" => "role-detail", "guard_name" => "admin", "alias" => "角色详情"],
        ["name" => "role-add", "guard_name" => "admin", "alias" => "角色添加"],
        ["name" => "role-update", "guard_name" => "admin", "alias" => "角色更新"],
        ["name" => "role-delete", "guard_name" => "admin", "alias" => "角色删除"],
        ["name" => "role-permission", "guard_name" => "admin", "alias" => "角色的权限"],
        ["name" => "role-add.permission", "guard_name" => "admin", "alias" => "角色添加权限"],


        ["name" => "order-list", "guard_name" => "admin", "alias" => "订单列表"],
        ["name" => "order-detail", "guard_name" => "admin", "alias" => "订单详情"],
        ["name" => "order-count.num", "guard_name" => "admin", "alias" => "统计文字"],
        ["name" => "order-add", "guard_name" => "admin", "alias" => "订单添加"],
        ["name" => "order-update", "guard_name" => "admin", "alias" => "订单更新"],
        ["name" => "order-statistics", "guard_name" => "admin", "alias" => "订单统计"],
        ["name" => "order-status", "guard_name" => "admin", "alias" => "修改状态"],
        ["name" => "order-edit.name", "guard_name" => "admin", "alias" => "分配编辑"],
        ["name" => "order-manuscript", "guard_name" => "admin", "alias" => "稿件上传"],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->createAdmin();
      //  $this->createRole();
       // $this->createPermission();
     //   $this->associateRolePermissions();
    }

    public function createAdmin()
    {
        \App\Http\Model\User::truncate();
        \App\Http\Model\User::create([
            'name' => 'admin',
            'email' => 'admin@qq.com',
            'password' => \Illuminate\Support\Facades\Hash::make('admin'), // secret
        ]);
    }

    public function createRole()
    {
        \App\Http\Model\Role::query()->delete();
        \App\Http\Model\Role::create([
            'name' => 'admin',
            'guard_name' => 'admin'
        ]);
        \App\Http\Model\Role::create([
            'name' => '客服',
            'guard_name' => 'admin',
            "alias" => "staff"
        ]);
        \App\Http\Model\Role::create([
            'name' => '编辑',
            'guard_name' => 'admin',
            "alias" => "edit"
        ]);
    }

    public function createPermission()
    {
        \App\Http\Model\Permission::query()->delete();
        foreach ($this->permission as $permission) {
            \App\Http\Model\Permission::create($permission);
        }
    }

    private function associateRolePermissions()
    {
        $role = \App\Http\Model\Role::where('name', 'admin')->first();

        \App\Http\Model\User::first()->assignRole($role->name);

        foreach ($this->permission as $permission) {
            $role->givePermissionTo($permission['name']);
        }
    }
}
