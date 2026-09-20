<?php

namespace App\Http\Controllers\Admin;

use App\Http\Constants\CodeMessageConstants;
use App\Http\Controllers\Controller;
use App\Http\Model\Department;
use App\Http\Model\User;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * FunctionName：create
     * Description：创建
     * Author：cherish
     * @return mixed
     */
    public function create()
    {
        $this->request->validate([
            'name' => ['required', 'unique:' . (new Department())->getTable() . ",name"],
            'parent_id' => 'required', 'exists:' . (new Department())->getTable() . ",id"
        ]);
        $data = $this->request->input();
        if ($data['parent_id'] == 0) {
            $data['level'] = 1;
        } else {
            $dep = Department::where('id', $data['parent_id'])->first();
            $data['level'] = $dep['level'] + 1;
            if ($dep['level'] == 3)
                throw \ExceptionFactory::business(CodeMessageConstants::CHECK_LEVEL);
        }

        return Department::create($data);
    }

    /**
     * FunctionName：update
     * Description：更新
     * Author：cherish
     * @return mixed
     */
    public function update()
    {
        $this->request->validate([
            'id' => 'required', 'exists:' . (new Department())->getTable() . ',id',
            'name' => ['required', 'unique:' . (new Department())->getTable() . ",name," . $this->request->input('id')],
//            'parent_id' => 'exists:' . (new Department())->getTable() . ",id"
        ]);
        return Department::where('id', $this->request->input('id'))->update($this->request->input());
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
            'id' => ['required', 'exists:' . (new Department())->getTable() . ',id'],
        ]);
        $department = Department::where('parent_id', $this->request->input('id'))->first();
        $departmentAll = Department::where('id', $this->request->input('id'))->first();
        if ($departmentAll['alias'])
            throw \ExceptionFactory::business(CodeMessageConstants::IS_ADMIN_CHECK);

        if ($department) {
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_CHILD_CLASSIFY);

        }
        $order = User::where("department_id", $this->request->input('id'))->first();
        if ($order) {
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_CLASSIFY_DE);
        }
        return Department::where('id', $this->request->input('id'))->delete();
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
        $classify = new Department();
        $classify = $classify->with('children');
        if ($this->request->input('name')) {
            $classify = $classify->where('name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('id')) {
            $classify = $classify->where('id', '=', $this->request->input('id'));
        }
        return $classify->where('parent_id', 0)->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], "page", $page);
    }

    /**
     * FunctionName：getThreeCalssifyAll
     * Description：获取三级的数据并返回新的数组
     * Author：cherish
     * @return \Illuminate\Support\Collection
     */

    public function getThreeCalssifyAll()
    {
//        $all = Classify::where('parent_id', 0)->with('children')->get();
//        return collect($all)->pluck('children.*.children')->collapse()->collapse();

        $classify = new Department();
        $classify = $classify->with('children');
        return $classify->where('parent_id', 0)->orderBy('created_at', 'desc')->get();
    }
}
