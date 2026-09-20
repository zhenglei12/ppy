<?php


namespace App\Http\Controllers\Admin;


use App\Http\Constants\CodeMessageConstants;
use App\Http\Model\Order;
use App\Http\Model\Role;
use App\Http\Model\User;
use App\Http\Services\ExportsStaffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StaffControllers
{
    public function __construct(Request $request)
    {
        $this->request = $request;
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
        $pageSize = $this->request->input('pageSize') ?? 15;
        $order = new Order();
        $all = new Order();
        if ($this->request->input('name')) {
            $order = $order->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
            $all = $all->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
            $all = $all->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }

        $user = \Auth::user();
        if ($user->roles->pluck('alias')[0] == 'staff') {
            $order = $order->where('staff_name', $user['name']);
            $all = $all->where('staff_name', $user['name']);
        } else {
            $role = Role::where('alias', "staff")->first();
            $userName = User::role($role['name'])->pluck('name');
            $order = $order->whereIn('staff_name', $userName);
            $all = $all->whereIn('staff_name', $userName);
        }

        $data['amount_count'] = $all->sum('amount');
        $data['received_amount_count'] = $all->sum('received_amount');
        $data['receipt_amount_count'] = number_format($all->sum('amount') - $all->sum('received_amount'), 2, '.', '');
        $data['after_amount_count'] = $all->sum('othen_amount');
        $data['alter_word_count'] = $all->sum('word_number');
        $data['all_finish'] = $all->count();
        $data['list'] = $order->select(
            "staff_name",
            DB::raw('sum(amount) as amount'),
            DB::raw('sum(received_amount) as received_amount'),
            DB::raw('sum(ifnull(amount,0)) - sum(ifnull(received_amount,0)) as receipt_time'),
            DB::raw('sum(othen_amount) as after_banlace'),
            DB::raw('sum(word_number) as word_number'),
            DB::raw("	count(status) as all_finish") //订单完成(总已经完成数量)
        )->groupBy('staff_name')->orderBy('amount', 'desc')->paginate($pageSize, ['*'], "page", $page);
        return $data;
    }


    public function export()
    {
        $page = $this->request->input('page') ?? 1;
        $pageSize = $this->request->input('pageSize') ?? 15;
        $order = new Order();
        $all = new Order();
        if ($this->request->input('name')) {
            $order = $order->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
            $all = $all->where('staff_name', 'like', "%" . $this->request->input('name') . "%");
        }
        if ($this->request->input('end_time')) {
            $order = $order->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
            $all = $all->whereDate('created_at', '<=', $this->request->input('end_time'))->whereDate('created_at', '>=', $this->request->input('created_at'));
        }
        $role = Role::where('alias', "staff")->first();
        $userName = User::role($role['name'])->pluck('name');
        $order = $order->whereIn('staff_name', $userName);
        $all = $all->whereIn('staff_name', $userName);
        $data['amount_count'] = $all->sum('amount');
        $data['received_amount_count'] = $all->sum('received_amount');
        $data['receipt_amount_count'] = $all->sum('receipt_time');
        $data['after_amount_count'] = $all->sum('after_banlace');
        $data['list'] = $order->select(
            "staff_name",
            DB::raw('sum(amount) as amount'),
            DB::raw('sum(received_amount) as received_amount'),
            DB::raw('sum(receipt_time) as receipt_time'),
            DB::raw('sum(after_banlace) as after_banlace')
        )->groupBy('staff_name')->get();
        if (count($data) < 1)
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_ORDER_NULL);
        if (count($data) > 2000)
            throw \ExceptionFactory::business(CodeMessageConstants::CHECK_ORDER_NUM);
        $filename = '员工统计列表.xls';
        return Excel::download(new ExportsStaffService($data['list']), $filename);
    }
}
