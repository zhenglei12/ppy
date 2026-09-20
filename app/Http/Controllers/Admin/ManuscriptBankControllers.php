<?php


namespace App\Http\Controllers\Admin;


use App\Http\Model\Classify;
use App\Http\Model\ManuscriptBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ManuscriptBankControllers
{

    /**
     * FunctionName：create
     * Description：创建
     * Author：cherish
     * @return mixed
     */
    public function create(Request $request)
    {
        $request->validate([
            'subject' => 'required',
            'word_number' => 'required',
            'classify_id' => 'required',
            'manuscript' => 'required'
        ]);
        $data = $request->input();
        $data['classify_local_id'] = $this->getClassifyId($data['classify_id']);
        $data['classify_id'] =  implode(",", $data['classify_id']);
        return ManuscriptBank::create($data);
    }

    /**
     * FunctionName：getClassifyId
     * Description:处理
     * Author：cherish
     * @param $data
     * @return mixed|null
     */
    public function getClassifyId($data){
        if(isset($data[2])){
            return $data[2];
        }
        return null;
    }

    /**
     * FunctionName：update
     * Description：更新
     * Author：cherish
     * @return mixed
     */
    public function update(Request $request)
    {
        $this->request->validate([
            'id' => 'required', 'exists:' . (new ManuscriptBank())->getTable() . ',id',
            'subject' => 'required',
            'word_number' => 'required',
            'manuscript' => 'required',
            'classify_id' => 'required'
        ]);
        $data = $request->input();
        $data['classify_local_id'] = $this->getClassifyId($data['classify_id']);
        $data['classify_id'] =  implode(",", $data['classify_id']);
        return ManuscriptBank::where('id', $request->input('id'))->update($data);
    }


    /**
     * FunctionName：delete
     * Description：删除
     * Author：cherish
     * @return mixed
     */
    public function delete(Request $request)
    {
        $this->request->validate([
            'id' => ['required', 'exists:' . (new ManuscriptBank())->getTable() . ',id'],
        ]);
        return ManuscriptBank::where('id', $request->input('id'))->delete();
    }

    /**
     * FunctionName：list
     * Description：列表
     * Author：cherish
     * @return mixed
     */
    public function list(Request $request)
    {
        $page = $request->input('page') ?? 1;
        $pageSize = $request->input('pageSize') ?? 10;
        $manus = new ManuscriptBank();
        $manus = $manus->with('classify');
        if ($request->input('subject')) {
            $manus = $manus->where('subject', 'like', "%" . $request->input('name') . "%");
        }
        if ($request->input('classify_id')) {
            $manus = $manus->where('classify_id', 'like', "%" . $request->input('classify_id') . "%");
        }
        return $manus->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], "page", $page);
    }

}
