<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['namespace' => 'Admin', 'middleware' => 'cross'], function () {
    Route::post('auth/login', 'UserControllers@login');
});

Route::group(['namespace' => 'Admin', 'middleware' => ['auth:sanctum', 'cross']], function () {
    Route::get('user/detail', 'UserControllers@detail');
    Route::get('user/permission', 'UserControllers@permission');
    Route::post('auth/logout', 'UserControllers@logout');
    Route::post('permission/all', 'PermissionControllers@all');
    Route::post('role/all', 'RoleControllers@list');
    //  Route::post("pub/user/role", "UserControllers@roleList");
    Route::post('pub/role/user_list', 'RoleControllers@userList');
    Route::post('qiniu/auth', 'UploadController@qiniuAuth'); //获取图片上传token
    Route::post('public/classify/list', 'ClassifyControllers@getThreeCalssifyAll');

    Route::post('public/department/list', 'DepartmentController@getThreeCalssifyAll');
});

Route::group(['namespace' => 'Admin', 'middleware' => ['cross', 'auth:sanctum', 'ly.permission']], function () {
    // 总部经营系统新业务接口（客户、订单、财务、交付）
    Route::prefix('business')->group(function () {
        Route::post('customers/list', 'Business\\CustomerController@index')->name('business.customers.list')->defaults('permission', 'sales.customer.view');
        Route::get('customers/detail', 'Business\\CustomerController@show')->name('business.customers.detail')->defaults('permission', 'sales.customer.view');
        Route::post('customers/create', 'Business\\CustomerController@store')->name('business.customers.create')->defaults('permission', 'sales.customer.manage');
        Route::post('customers/update', 'Business\\CustomerController@update')->name('business.customers.update')->defaults('permission', 'sales.customer.manage');
        Route::post('customers/delete', 'Business\\CustomerController@destroy')->name('business.customers.delete')->defaults('permission', 'sales.customer.manage');
        Route::get('sales/dashboard', 'Business\\SalesController@dashboard')->name('business.sales.dashboard')->defaults('permission', 'sales.order.view');
        Route::get('overview/dashboard', 'Business\\OverviewController@dashboard')->name('business.overview.dashboard')->defaults('permission', 'sales.order.view');

        Route::post('orders/list', 'Business\\OrderController@index')->name('business.orders.list')->defaults('permission', 'sales.order.view');
        Route::get('orders/detail', 'Business\\OrderController@show')->name('business.orders.detail')->defaults('permission', 'sales.order.view');
        Route::post('orders/create', 'Business\\OrderController@store')->name('business.orders.create')->defaults('permission', 'sales.order.create');
        Route::post('orders/update', 'Business\\OrderController@update')->name('business.orders.update')->defaults('permission', 'sales.order.update');
        Route::post('orders/delete', 'Business\\OrderController@destroy')->name('business.orders.delete')->defaults('permission', 'sales.order.update');
        Route::post('orders/transition', 'Business\\OrderController@transition')->name('business.orders.transition')->defaults('permission', 'sales.order.view');
        Route::post('orders/assign', 'Business\\OrderController@assign')->name('business.orders.assign')->defaults('permission', 'sales.order.assign');

        Route::get('finance/dashboard', 'Business\\FinanceController@dashboard')->name('business.finance.dashboard')->defaults('permission', 'finance.view');
        Route::post('finance/payment-plans/list', 'Business\\FinanceController@plans')->name('business.finance.plans.list')->defaults('permission', 'finance.view');
        Route::post('finance/payment-plans/save', 'Business\\FinanceController@savePlan')->name('business.finance.plans.save')->defaults('permission', 'finance.receivable.manage');
        Route::post('finance/payments/list', 'Business\\FinanceController@payments')->name('business.finance.payments.list')->defaults('permission', 'finance.view');
        Route::post('finance/payments/create', 'Business\\FinanceController@createPayment')->name('business.finance.payments.create')->defaults('permission', 'finance.view');
        Route::post('finance/payments/confirm', 'Business\\FinanceController@confirmPayment')->name('business.finance.payments.confirm')->defaults('permission', 'finance.payment.confirm');
        Route::post('finance/invoices/list', 'Business\\FinanceController@invoices')->name('business.finance.invoices.list')->defaults('permission', 'finance.view');
        Route::post('finance/invoices/save', 'Business\\FinanceController@saveInvoice')->name('business.finance.invoices.save')->defaults('permission', 'finance.invoice.manage');
        Route::post('finance/refunds/list', 'Business\\FinanceController@refunds')->name('business.finance.refunds.list')->defaults('permission', 'finance.view');
        Route::post('finance/refunds/create', 'Business\\FinanceController@createRefund')->name('business.finance.refunds.create')->defaults('permission', 'finance.refund.manage');
        Route::post('finance/refunds/process', 'Business\\FinanceController@processRefund')->name('business.finance.refunds.process')->defaults('permission', 'finance.refund.manage');

        Route::get('delivery/dashboard', 'Business\\DeliveryController@dashboard')->name('business.delivery.dashboard')->defaults('permission', 'delivery.project.view');
        Route::post('delivery/projects/list', 'Business\\DeliveryController@projects')->name('business.delivery.projects.list')->defaults('permission', 'delivery.project.view');
        Route::get('delivery/projects/detail', 'Business\\DeliveryController@projectDetail')->name('business.delivery.projects.detail')->defaults('permission', 'delivery.project.view');
        Route::post('delivery/projects/create', 'Business\\DeliveryController@createProject')->name('business.delivery.projects.create')->defaults('permission', 'delivery.project.assign');
        Route::post('delivery/projects/update', 'Business\\DeliveryController@updateProject')->name('business.delivery.projects.update')->defaults('permission', 'delivery.project.manage');
        Route::post('delivery/milestones/update', 'Business\\DeliveryController@updateMilestone')->name('business.delivery.milestones.update')->defaults('permission', 'delivery.project.manage');
        Route::post('delivery/tasks/list', 'Business\\DeliveryController@tasks')->name('business.delivery.tasks.list')->defaults('permission', 'delivery.project.view');
        Route::post('delivery/tasks/save', 'Business\\DeliveryController@saveTask')->name('business.delivery.tasks.save')->defaults('permission', 'delivery.task.manage');
        Route::post('delivery/tasks/status', 'Business\\DeliveryController@taskStatus')->name('business.delivery.tasks.status')->defaults('permission', 'delivery.task.manage');
        Route::post('delivery/evidences/submit', 'Business\\DeliveryController@submitEvidence')->name('business.delivery.evidences.submit')->defaults('permission', 'delivery.evidence.submit');
        Route::post('delivery/evidences/review', 'Business\\DeliveryController@reviewEvidence')->name('business.delivery.evidences.review')->defaults('permission', 'delivery.quality.review');

        Route::post('payroll/sheets/list', 'Business\\PayrollController@sheets')->name('business.payroll.sheets.list')->defaults('permission', 'hr.payroll.view');
        Route::get('payroll/sheets/detail', 'Business\\PayrollController@sheetDetail')->name('business.payroll.sheets.detail')->defaults('permission', 'hr.payroll.view');
        Route::post('payroll/sheets/create', 'Business\\PayrollController@createSheet')->name('business.payroll.sheets.create')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/sheets/update', 'Business\\PayrollController@updateSheet')->name('business.payroll.sheets.update')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/sheets/delete', 'Business\\PayrollController@deleteSheet')->name('business.payroll.sheets.delete')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/items/list', 'Business\\PayrollController@items')->name('business.payroll.items.list')->defaults('permission', 'hr.payroll.view');
        Route::get('payroll/items/detail', 'Business\\PayrollController@itemDetail')->name('business.payroll.items.detail')->defaults('permission', 'hr.payroll.view');
        Route::post('payroll/items/create', 'Business\\PayrollController@createItem')->name('business.payroll.items.create')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/items/update', 'Business\\PayrollController@updateItem')->name('business.payroll.items.update')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/items/delete', 'Business\\PayrollController@deleteItem')->name('business.payroll.items.delete')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/sheets/submit-manager-review', 'Business\\PayrollController@submitManagerReview')->name('business.payroll.sheets.submit-manager-review')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/items/manager-review', 'Business\\PayrollController@managerReview')->name('business.payroll.items.manager-review')->defaults('permission', 'hr.payroll.manager.confirm');
        Route::post('payroll/sheets/submit-finance-review', 'Business\\PayrollController@submitFinanceReview')->name('business.payroll.sheets.submit-finance-review')->defaults('permission', 'hr.payroll.hr.manage');
        Route::post('payroll/sheets/finance-review', 'Business\\PayrollController@financeReview')->name('business.payroll.sheets.finance-review')->defaults('permission', 'hr.payroll.finance.review');
        Route::post('payroll/sheets/admin-review', 'Business\\PayrollController@adminReview')->name('business.payroll.sheets.admin-review')->defaults('permission', 'hr.payroll.admin.approve');
        Route::post('payroll/items/admin-adjust', 'Business\\PayrollController@adminAdjust')->name('business.payroll.items.admin-adjust')->defaults('permission', 'hr.payroll.admin.approve');
        Route::post('payroll/items/employee-action', 'Business\\PayrollController@employeeAction')->name('business.payroll.items.employee-action')->defaults('permission', 'hr.payroll.employee.confirm');
        Route::post('payroll/items/appeal-resolve', 'Business\\PayrollController@resolveAppeal')->name('business.payroll.items.appeal-resolve')->defaults('permission', 'hr.payroll.appeal.resolve');
        Route::post('payroll/sheets/mark-paid', 'Business\\PayrollController@markPaid')->name('business.payroll.sheets.mark-paid')->defaults('permission', 'hr.payroll.pay');
    });

    Route::post('role/list', 'RoleControllers@list')->name('role-list');
    Route::get('role/detail', 'RoleControllers@detail')->name('role-detail');
    Route::post('role/add', 'RoleControllers@add')->name('role-add');
    Route::post('role/update', 'RoleControllers@update')->name('role-update');
    Route::post('role/delete', 'RoleControllers@delete')->name('role-delete');
    Route::post('role/permission', 'RoleControllers@permission')->name('role-permission');
    Route::post('role/add/permission', 'RoleControllers@addPermission')->name('role-add.permission');

    Route::post('user/list', 'UserControllers@list')->name('user-list');
    Route::match(['get', 'post'], 'user/statistics', 'UserControllers@statistics')->name('user.statistics')->defaults('permission', 'hr.employee.view');
    Route::get('user/personal/detail', 'UserControllers@personalDetail')->name('user-personal.detail');
    Route::post('user/update', 'UserControllers@update')->name('user-update');
    Route::post('user/add', 'UserControllers@add')->name('user-add');
    Route::post('user/delete', 'UserControllers@delete')->name('user-delete');
    Route::post('user/role/list', 'UserControllers@roleList')->name('user-role.list');
    Route::post('user/add/role', 'UserControllers@addRole')->name('user-add.role');

    Route::post('order/list', 'OrderControllers@list')->name('order-list');
    Route::post('order/delete', 'OrderControllers@delete')->name('order-delete');
    Route::get('order/detail', 'OrderControllers@detail')->name('order-detail');
    Route::post('order/add', 'OrderControllers@add')->name('order-add');
    Route::post('order/count_num', 'OrderControllers@count_num')->name('order-count.num');
    Route::post('order/update', 'OrderControllers@update')->name('order-update');
    Route::post('order/statistics', 'OrderControllers@statistics')->name('order-statistics');
    Route::post('order/status', 'OrderControllers@status')->name('order-status');
    Route::post('order/logs', 'OrderControllers@logs')->name('order-logs');
    Route::post('order/edit_name', 'OrderControllers@editName')->name('order-edit.name');
    Route::post('order/manuscript', 'OrderControllers@manuscript')->name('order-manuscript');
    Route::post('order/manuscript/score', 'OrderControllers@manuscript_score')->name('order-manuscript.score');

    Route::post('device/list', 'DeviceController@list')->name('device-list');
    Route::post('device/delete', 'DeviceController@delete')->name('device-delete');
    Route::get('device/detail', 'DeviceController@detail')->name('device-detail');
    Route::post('device/add', 'DeviceController@add')->name('device-add');
    Route::post('device/update', 'DeviceController@update')->name('device-update');
    Route::post('device/log/list', 'DeviceController@logList')->name('device-log.list');

    Route::post('operation_account/list', 'OperationAccountController@list')->name('operation_account-list');
    Route::post('operation_account/delete', 'OperationAccountController@delete')->name('operation_account-delete');
    Route::get('operation_account/detail', 'OperationAccountController@detail')->name('operation_account-detail');
    Route::post('operation_account/add', 'OperationAccountController@add')->name('operation_account-add');
    Route::post('operation_account/update', 'OperationAccountController@update')->name('operation_account-update');
    Route::post('operation_account/log/list', 'OperationAccountController@logList')->name('operation_account-log.list');
    //    Route::post("order/hard_grade", "OrderControllers@grade")->name('order-hard.grade');

    Route::post('order/after', 'OrderControllers@after')->name('order-after');

    Route::post('classify/list', 'ClassifyControllers@list')->name('classify-list');
    Route::post('classify/delete', 'ClassifyControllers@delete')->name('classify-delete');
    Route::post('classify/update', 'ClassifyControllers@update')->name('classify-update');
    Route::post('classify/add', 'ClassifyControllers@create')->name('classify-create');

    Route::post('order/export', 'OrderControllers@export')->name('order-export');

    Route::post('edit/order/list', 'EditControllers@orderList')->name('edit-statistics.order.list');
    Route::post('edit/statistics/all/list', 'EditControllers@allList')->name('edit-statistics.all.list');
    Route::post('edit/statistics/day/list', 'EditControllers@dayList')->name('edit-statistics.day.list');

    Route::post('staff/statistics/list', 'StaffControllers@list')->name('staff-statistics.list');
    Route::post('staff/statistics/list/export', 'StaffControllers@export')->name('staff-statistics.export');

    Route::post('manuscript_bank/list', 'ManuscriptBankControllers@list')->name('manuscript_bank-list');
    Route::post('manuscript_bank/delete', 'ManuscriptBankControllers@delete')->name('manuscript_bank-delete');
    Route::post('manuscript_bank/update', 'ManuscriptBankControllers@update')->name('manuscript_bank-update');
    Route::post('manuscript_bank/add', 'ManuscriptBankControllers@create')->name('manuscript_bank-create');

    Route::post('department/list', 'DepartmentController@list')->name('department-list');
    Route::post('department/delete', 'DepartmentController@delete')->name('department-delete');
    Route::post('department/update', 'DepartmentController@update')->name('department-update');
    Route::post('department/add', 'DepartmentController@create')->name('department-create');
});
