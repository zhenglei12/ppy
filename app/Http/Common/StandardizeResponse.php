<?php

namespace App\Http\Common;

use Closure;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StandardizeResponse
{
    private $message = ['code' => 0, 'message' => 'success'];

    private $options = [];

    public function handle(Request $request, Closure $next)
    {

        $response = $next($request);
        if ($response instanceof BinaryFileResponse) {
            return $response;
        }
        $content = $response->getOriginalContent();
        if ($response->status() < 200 || $response->status() >= 300) {
            return $response;
        }

        if ($content instanceof LengthAwarePaginator) {     //验证是否分页返回
            $content = $content->toArray();
            $data['list'] = $content['data'];
            $data['last_page'] = $content['last_page'];
            $data['per_page'] = $content['per_page'];
            $data['current_page'] = $content['current_page'];
            $data['total'] = $content['total'];
        } else if (is_array($content) && isset($content['code']) && $content['code'] == 422) {   //判断是否是参数错误
            return $response;
        } else if (is_array($content) && isset($content['type']) && $content['type'] == 'business') { //判断是否业务返回错误
            unset($content['type']);
            return response()->json($content);
        } elseif (is_array($content)) {       //验证数组
            if (array_key_exists('options', $content) && is_array($content['options'])) {
                $this->options = array_merge($this->options, $content['options']);
                unset($content['options']);
            }
            $data = $content;
        } else if (is_null($content)) {  //验证NULL
            $data = (object)$content;
        } else if ($content instanceof Model) {
            $data = $content->toArray();
        } else if ($content instanceof \Illuminate\Support\Collection) {
            $data['list'] = $content;
        } else if (is_numeric($content)) {
            $data['list'] = $content;
        } else {
            throw new \Exception('返回数据格式错误');
        }
        if(is_array($data) && isset($data['customize'])){
            $jsonData =  array_diff_key($data, ['customize'=> true]);
        }else{
            $result['data'] = $data;
            $jsonData = array_merge($this->options, $this->message, $result);
        }

        return response()->json($jsonData, 200);

    }
}

