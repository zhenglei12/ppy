<?php

namespace App\Http\Middleware;

use App\Http\Constants\CodeMessageConstants;
use Auth;
use Route;

class AuthPermission
{
    /**
     * FunctionName：handle
     * Description：认证权限
     * Author：cherish
     *
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        // 业务接口使用唯一的路由名称，并通过 defaults.permission 复用权限点。
        // 旧接口没有设置 permission 时继续使用路由名称，保持向后兼容。
        $permission = Route::current()->defaults['permission'] ?? Route::currentRouteName();

        if (Auth::user()->name == 'admin') {
            return $next($request);
        }

        if (Auth::user()->can($permission)) {
            return $next($request);
        }
        throw \ExceptionFactory::business(CodeMessageConstants::FORBIDDEN);
    }
}
