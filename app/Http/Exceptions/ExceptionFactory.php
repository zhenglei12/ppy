<?php


namespace App\Http\Exceptions;


class ExceptionFactory
{
    public function business(array $mixed, array $data = []){
        return new BusinessException($mixed['message'] ?? 'business_error', $mixed['code'] ?? 400, $data);
    }
}
