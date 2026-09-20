<?php


namespace App\Http\Controllers\Admin;


use App\Http\Controllers\Controller;
use App\Http\Model\Permission;

class PermissionControllers extends Controller
{
    /**
     * FunctionName：all
     * Description：所有权限
     * Author：cherish
     * @return mixed
     */
    public function all()
    {
        return Permission::get();
    }
}
