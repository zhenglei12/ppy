<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_sheets', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('calculated_at')->comment('人事提交用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable()->after('submitted_by')->comment('人事提交时间');
            $table->foreignId('finance_reviewed_by')->nullable()->after('submitted_at')->comment('财务复核用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('finance_reviewed_at')->nullable()->after('finance_reviewed_by')->comment('财务复核时间');
            $table->string('finance_comment', 1000)->nullable()->after('finance_reviewed_at')->comment('财务复核意见');
            $table->foreignId('admin_approved_by')->nullable()->after('finance_comment')->comment('超级管理员终审用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('admin_approved_at')->nullable()->after('admin_approved_by')->comment('超级管理员终审时间');
            $table->string('admin_comment', 1000)->nullable()->after('admin_approved_at')->comment('超级管理员终审意见');
            $table->string('rejection_reason', 1000)->nullable()->after('admin_comment')->comment('最近一次退回原因');
            $table->dateTime('completed_at')->nullable()->after('locked_at')->comment('全员确认完成时间');
            $table->foreignId('paid_by')->nullable()->after('completed_at')->comment('工资发放确认用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at')->nullable()->after('paid_by')->comment('工资发放确认时间');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->string('employee_name', 255)->nullable()->after('user_id')->comment('员工姓名快照');
            $table->string('department_name', 255)->nullable()->after('employee_name')->comment('部门名称快照');
            $table->string('position_name', 100)->nullable()->after('department_name')->comment('岗位名称快照');
            $table->string('salary_plan_version', 32)->nullable()->after('position_name')->comment('员工薪资方案版本快照');
            $table->string('manager_status', 32)->default('pending')->index()->after('calculation_detail')->comment('上级确认状态：pending待确认、approved已确认、rejected已退回');
            $table->foreignId('manager_confirmed_by')->nullable()->after('manager_status')->comment('业绩确认上级用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('manager_confirmed_at')->nullable()->after('manager_confirmed_by')->comment('业绩确认时间');
            $table->string('manager_comment', 1000)->nullable()->after('manager_confirmed_at')->comment('上级确认意见');
            $table->foreignId('employee_confirmed_by')->nullable()->after('appeal_result')->comment('员工确认用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('employee_confirmed_at')->nullable()->after('employee_confirmed_by')->comment('员工确认时间');
            $table->foreignId('appeal_handled_by')->nullable()->after('employee_confirmed_at')->comment('申诉处理用户ID')->constrained('users')->nullOnDelete();
            $table->dateTime('appeal_handled_at')->nullable()->after('appeal_handled_by')->comment('申诉处理时间');
        });

        DB::statement("ALTER TABLE `payroll_sheets` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT '工资表状态：draft人事草稿、manager_review上级确认、finance_review财务复核、admin_review终审、employee_confirmation员工确认、completed确认完成、paid已发放'");
        DB::statement("ALTER TABLE `payroll_items` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT '明细状态：pending待员工确认、confirmed员工已确认、appealing申诉中、paid已发放'");

        $this->seedPermissions();
    }

    private function seedPermissions(): void
    {
        $now = now();
        $permissions = [
            ['hr.payroll.hr.manage', '工资基础数据管理', '人事创建工资表并维护基础工资、补贴和考勤扣款'],
            ['hr.payroll.manager.confirm', '下属业绩确认', '上级确认本人部门及下级部门员工的业绩收入'],
            ['hr.payroll.finance.review', '工资财务复核', '财务复核税费、社保、公积金、退款冲回及最终金额'],
            ['hr.payroll.admin.approve', '工资终审锁定', '超级管理员终审并锁定工资表'],
            ['hr.payroll.employee.confirm', '员工工资确认', '员工确认或申诉本人工资明细'],
            ['hr.payroll.appeal.resolve', '工资申诉处理', '人事处理员工工资申诉并决定退回节点'],
            ['hr.payroll.pay', '工资发放确认', '财务确认工资已发放'],
        ];

        foreach ($permissions as $sort => [$name, $alias, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                [
                    'alias' => $alias,
                    'module' => 'hr',
                    'permission_type' => 'button',
                    'route_path' => null,
                    'sort' => 900 - $sort,
                    'status' => 1,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $assignments = [
            'hr' => ['hr.payroll.view', 'hr.payroll.hr.manage', 'hr.payroll.employee.confirm', 'hr.payroll.appeal.resolve'],
            'finance' => ['hr.payroll.view', 'hr.payroll.finance.review', 'hr.payroll.employee.confirm', 'hr.payroll.pay'],
            'sales_director' => ['hr.payroll.view', 'hr.payroll.manager.confirm', 'hr.payroll.employee.confirm'],
            'sales_manager' => ['hr.payroll.view', 'hr.payroll.manager.confirm', 'hr.payroll.employee.confirm'],
            'technical_director' => ['hr.payroll.view', 'hr.payroll.manager.confirm', 'hr.payroll.employee.confirm'],
            'sales' => ['hr.payroll.view', 'hr.payroll.employee.confirm'],
            'optimizer' => ['hr.payroll.view', 'hr.payroll.employee.confirm'],
            'assistant' => ['hr.payroll.view', 'hr.payroll.employee.confirm'],
            'telesales' => ['hr.payroll.view', 'hr.payroll.employee.confirm'],
        ];

        foreach ($assignments as $roleAlias => $permissionNames) {
            $roleId = DB::table('roles')->where('alias', $roleAlias)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        $adminRoleId = DB::table('roles')->where('alias', 'admin')->value('id');
        if ($adminRoleId) {
            foreach (DB::table('permissions')->where('module', 'hr')->where('name', 'like', 'hr.payroll.%')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
            }
        }

        // 项目约定角色 ID=1 为系统最高权限角色，显式写入全部工资权限。
        if (DB::table('roles')->where('id', 1)->exists()) {
            foreach (DB::table('permissions')->where('module', 'hr')->where('name', 'like', 'hr.payroll.%')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => 1]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appeal_handled_by');
            $table->dropConstrainedForeignId('employee_confirmed_by');
            $table->dropConstrainedForeignId('manager_confirmed_by');
            $table->dropColumn(['employee_name', 'department_name', 'position_name', 'salary_plan_version', 'manager_status', 'manager_confirmed_at', 'manager_comment', 'employee_confirmed_at', 'appeal_handled_at']);
        });
        Schema::table('payroll_sheets', function (Blueprint $table) {
            foreach (['submitted_by', 'finance_reviewed_by', 'admin_approved_by', 'paid_by'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['submitted_at', 'finance_reviewed_at', 'finance_comment', 'admin_approved_at', 'admin_comment', 'rejection_reason', 'completed_at', 'paid_at']);
        });
    }
};
