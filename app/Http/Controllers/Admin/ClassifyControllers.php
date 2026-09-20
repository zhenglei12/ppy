<?php


namespace App\Http\Controllers\Admin;


use App\Http\Constants\CodeMessageConstants;
use App\Http\Model\Classify;
use App\Http\Model\Order;
use App\Http\Services\ClassifyServices;
use Illuminate\Http\Request;

class ClassifyControllers
{
    public function __construct(Request $request, ClassifyServices $services)
    {
        $this->request = $request;
        $this->services = $services;
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
            'name' => 'required',
            'parent_id' => 'exists:' . (new Classify())->getTable() . ",id"
        ]);
        return Classify::create($this->request->input());
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
            'id' => 'required',
            'exists:' . (new Classify())->getTable() . ',id',
            'name' => 'required',
            'parent_id' => 'exists:' . (new Classify())->getTable() . ",id"
        ]);
        return Classify::where('id', $this->request->input('id'))->update($this->request->input());
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
            'id' => ['required', 'exists:' . (new Classify())->getTable() . ',id'],
        ]);
        $classify = Classify::where('parent_id', $this->request->input('id'))->first();

        if ($classify) {
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_CHILD_CLASSIFY);
        }
        $order = Order::where("classify_id", $this->request->input('id'))->first();
        if ($order) {
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_CLASSIFY);
        }
        return Classify::where('id', $this->request->input('id'))->delete();
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
        $classify = new Classify();
        $classify =  $classify->with('children');
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

        $classify = new Classify();
        $classify =  $classify->with('children');
        return $classify->where('parent_id', 0)->orderBy('created_at', 'desc')->get();
    }
}
