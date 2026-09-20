<?php


namespace App\Http\Exceptions;


use Illuminate\Support\Facades\Facade;

class ExceptionFactoryFacade extends Facade
{
    /**
     * 获取组件的注册名称。
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'ExceptionFactory';
    }
}
