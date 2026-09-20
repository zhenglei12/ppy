<?php


namespace App\Http\Services;


use App\Http\Constants\CodeMessageConstants;
use App\Http\Model\User;
use Laravel\Sanctum\PersonalAccessToken;

class UserServices
{
    /**
     * FunctionName：login
     * Description：登陆
     * Author：cherish
     * @param $name
     * @param $password
     * @return mixed
     */
    public function login($name, $password)
    {
        $user = User::where('name', $name)->first();
        if (!$user || !\Hash::check($password, $user->password)) {
            throw \ExceptionFactory::business(CodeMessageConstants::NAME_ERROR);
        }
        //   $this->deleteToken($user->id); //删除token
        $data['token'] = $user->createToken('admin')->plainTextToken; //生成新的token
        return $data;
    }

    /**
     * FunctionName：deleteToken
     * Description：删除token（单点登陆）
     * Author：cherish
     * @param $id
     */
    private function deleteToken($id)
    {
        PersonalAccessToken::query()->where("tokenable_type", User::class)
            ->where("name", "admin")
            ->where("tokenable_id", $id)
            ->delete();
        return;
    }
}
