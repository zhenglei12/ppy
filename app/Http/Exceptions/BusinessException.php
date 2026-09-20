<?php


namespace App\Http\Exceptions;


use Exception;

class BusinessException extends Exception
{
    private $httpCode = 200;

    private $error_data;

    private $_code;


    public function __construct($cause = 'BusinessException.', $code, $data = [])
    {
        parent::__construct($cause);
        $this->_code = $code;
        $this->error_data = $data;
    }

    public function getStatusCode()
    {
        return $this->_code;
    }

    /**
     * FunctionName：getResponse
     * Description：组装错误返回格式
     * Author：cherish
     * @param:data
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResponse()
    {
        $ret = [
            'message' => trans($this->getMessage(), $this->error_data),
            'data' => $this->error_data,
            'code' => $this->_code,
            'type' => 'business' //此参数验证是否为业务错误
        ];
        return response()->json($ret, $this->httpCode);
    }

}
