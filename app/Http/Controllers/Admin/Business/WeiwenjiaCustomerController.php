<?php

namespace App\Http\Controllers\Admin\Business;

use App\Console\Commands\SyncWeiwenjiaCustomers;
use App\Http\Controllers\Controller;
use App\Http\Model\Customer;
use Illuminate\Http\Request;

class WeiwenjiaCustomerController extends Controller
{
    public function index(Request $request)
    {
        return Customer::where('source', 'weiwenjia')->withCount('orders')->when($request->filled('keyword'), fn ($q) => $q->where('legal_name', 'like', '%'.$request->input('keyword').'%'))->latest('external_updated_at')->paginate($request->integer('pageSize', 20));
    }

    public function show(Request $request)
    {
        return Customer::where('source', 'weiwenjia')->with('contacts')->findOrFail($request->integer('id'));
    }

    public function sync()
    {
        $code = app(SyncWeiwenjiaCustomers::class)->handle();
        abort_if($code !== 0, 502, '第三方客户同步失败');
        return ['synced' => true];
    }
}
