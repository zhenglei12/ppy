<?php


namespace App\Http\Controllers\Admin;


use App\Http\Model\Order;
use App\Http\Model\Role;
use App\Http\Model\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditControllers
{
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * FunctionName：list
     * Description：总的统计
     * Author：cherish
     * @return mixed
     */
    public function allList()
    {
//        $page = $this->request->input('page') ?? 1;
//        $pageSize = $this->request->input('pageSize') ?? 10;
//        $order = new Order();
//        if ($this->request->input('name')) {
//            $order = $order->where('edit_name', 'like', "%" . $this->request->input('name') . "%");
//        }
//        if ($this->request->input('end_time')) {
//            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
//        }
//        if ($this->request->input('submission_time')) {
//            $order = $order->whereDate('submission_time', '<=', $this->request->input('submission_time'));
//        }
//        $role = Role::where('alias', "edit")->first();
//        $userName = User::role($role['name'])->pluck('name');
//        $order = $order->whereIn('edit_name', $userName);
//        return $order->select(
//            "edit_name",
//            DB::raw('sum(case when status = 1 then 1 else 0 end) as all_waiting_commit'), //总待提交数
//            DB::raw("sum(case when status = 2 then 1 else 0 end) as all_waiting_alter"), //打回修改(总待修改数量)
//            DB::raw("	sum(case when status = 3 then 1 else 0 end) as all_finish"), //订单完成(总已经完成数量)
//            DB::raw("	sum(case when status = 4 or status = 5  then 1 else 0 end) as all_commit") //提交客户(总已经提交数量)
//        )->groupBy('edit_name')->paginate($pageSize, ['*'], "page", $page);


        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 15;
        $order = new Order();
        $all = new Order();
        if ($this->request->input('staff_name')) {
            $order = $order->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
            $all = $all->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
        }

        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
            $all = $all->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        //   $role = Role::where('alias', "staff")->first();
        // $userName = User::role($role['name'])->pluck('name');
        //  $order = $order->whereIn('staff_name', $userName);
        // $all = $all->whereIn('staff_name', $userName);
        $data['amount_count'] = $all->sum('amount');
        $data['received_amount_count'] = $all->sum('received_amount');
        $data['receipt_amount_count'] = number_format($all->sum('amount') - $all->sum('received_amount'), 2, '.', '');
        $data['after_amount_count'] = $all->sum('othen_amount');
//        $data['list'] = $order->select(
//            "staff_name",
//            DB::raw('sum(amount) as amount'),
//            DB::raw('sum(received_amount) as received_amount'),
//            DB::raw('sum(ifnull(amount,0)) - sum(ifnull(received_amount,0)) as receipt_time'),
//            DB::raw('sum(after_banlace) as after_banlace')
//        )->groupBy('staff_name')->paginate($pageSize, ['*'], "page", $page);
        return $data;
    }


    /**
     * FunctionName：dayList
     * Description：单日统计
     * Author：cherish
     * @return mixed
     */
    public function dayList()
    {
//        $page = $this->request->input('page') ?? 1;
//        $pageSize = $this->request->input('pageSize') ?? 10;
//        $order = new Order();
//        $allOrder = new Order();
//        if ($this->request->input('name')) {
//            $order = $order->where('edit_name', 'like', "%" . $this->request->input('name') . "%");
//        }
//        if ($this->request->input('created_at')) {
//            $date =$this->request->input('created_at');
//        } else {
//            $date = date("Y-m-d");
//        }
//        $role = Role::where('alias', "edit")->first();
//        $userName = User::role($role['name'])->pluck('name');
//        $order = $order->whereIn('edit_name', $userName);
//
//        return $order->select(
//            "edit_name",
//            DB::raw("count(case when edit_submit_time like '%$date%' then edit_submit_time else null end) as commit"), //提交数量
//            DB::raw("sum(case when edit_submit_time like '%$date%' then word_number else 0 end) as commit_word_number"), //提交字数
//            DB::raw("count(case when edit_submit_time like '%$date%' then edit_submit_time else null end) as alter_number"), //修改数量
//            DB::raw("sum(case when edit_submit_time like '%$date%' then alter_word else 0 end) as alter_word_number"), //修改字数
//            DB::raw("count(case when created_at like '%$date%' then created_at else null end ) as num"), //数量
//            DB::raw("	sum(case when created_at like '%$date%' then amount else 0 end) as amount"),//金额
//            DB::raw("sum(case when created_at like '%$date%' then word_number else 0 end) as word_number") //字数
//        )->groupBy('edit_name')->paginate($pageSize, ['*'], "page", $page);
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 15;
        $order = new Order();
        $all = new Order();
        if ($this->request->input('name')) {
            $order = $order->where('edit_name', 'like', "%" . $this->request->input('name') . "%");
            $all = $all->where('edit_name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
            $all = $all->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        $user = \Auth::user();
        if ($user->roles->pluck('alias')[0] == 'edit') {
            $order = $order->where('edit_name', $user['name']);
            $all = $all->where('edit_name', $user['name']);
        } else {
            $role = Role::where('alias', "edit")->first();
            $userName = User::role($role['name'])->pluck('name');
            $order = $order->whereIn('edit_name', $userName);
            $all = $all->whereIn('edit_name', $userName);
        }

        $data['amount_count'] = $all->sum('amount');
        $data['received_amount_count'] = $all->sum('received_amount');
        $data['receipt_amount_count'] = number_format($all->sum('amount') - $all->sum('received_amount'), 2, '.', '');
        $data['after_amount_count'] = $all->sum('othen_amount');
        $data['alter_word_count'] = $all->sum('word_number');
        $data['all_finish'] = $all->where('status', 3)->count();
        $data['list'] = $order->select(
            "edit_name",
            DB::raw('sum(amount) as amount'),
            DB::raw('sum(received_amount) as received_amount'),
            DB::raw('sum(ifnull(amount,0)) - sum(ifnull(received_amount,0)) as receipt_time'),
            DB::raw('sum(ifnull(othen_amount,0)) as after_banlace'),
            DB::raw('sum(CASE WHEN status = 5 THEN word_number  ELSE 0 END) as word_number'),
            //  DB::raw('sum(ifnull(word_number,0)) as word_number'),
            DB::raw("	sum(case when status = 5 then 1 else 0 end) as all_finish") //订单完成(总已经完成数量)
        )->groupBy('edit_name')->orderBy('amount', 'desc')->paginate($pageSize, ['*'], "page", $page);
        return $data;
    }

    public function orderList()
    {
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 10;
        $order = new Order();
        if ($this->request->input('subject')) {
            $order = $order->where('subject', 'like', "%" . $this->request->input('subject') . "%");
        }
        if ($this->request->input('word_number')) {
            $order = $order->where('word_number', $this->request->input('word_number'));
        }
        if ($this->request->input('id')) {
            $order = $order->where('id', '=', $this->request->input('id'));
        }
        if ($this->request->input('classify_id')) {
            $order = $order->where('classify_id', 'like', "%" . $this->request->input('classify_id') . "%");
        }

        $order = $order->where('status', '>=', 3);

        if ($this->request->input('created_at')) {
            $order = $order->where('created_at', 'like', "%" . $this->request->input('created_at') . "%");
        }
        return $order->orderBy('created_at', 'desc')->with('classify')->paginate($pageSize, ['*'], "page", $page);
    }
}


