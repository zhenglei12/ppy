<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $roles = [
            ['alias' => 'admin', 'name' => '超级管理员', 'sort' => 100, 'data_scope' => 'all', 'description' => '总部系统全部模块、全部数据和系统配置权限'],
            ['alias' => 'sales_director', 'name' => '销售总监', 'sort' => 95, 'data_scope' => 'all', 'description' => '查看销售经营结果、风险、审批和团队分配'],
            ['alias' => 'sales_manager', 'name' => '销售主管', 'sort' => 90, 'data_scope' => 'team', 'description' => '负责销售团队过程管理、客户归属和订单审核'],
            ['alias' => 'sales', 'name' => '销售', 'sort' => 85, 'data_scope' => 'self', 'description' => '负责客户推进、销售入单、回款跟进和续费'],
            ['alias' => 'technical_director', 'name' => '技术总监', 'sort' => 80, 'data_scope' => 'all', 'description' => '负责交付分单、质量、延期与重大风险处理'],
            ['alias' => 'optimizer', 'name' => '优化师', 'sort' => 75, 'data_scope' => 'self', 'description' => '负责项目方案、客户价值、复盘和经营推进'],
            ['alias' => 'assistant', 'name' => '优化师助理', 'sort' => 70, 'data_scope' => 'self', 'description' => '负责资料、内容、发布执行和交付凭证'],
            ['alias' => 'finance', 'name' => '财务主管', 'sort' => 65, 'data_scope' => 'all', 'description' => '负责到账、应收、发票、退款和工资财务复核'],
            ['alias' => 'hr', 'name' => '人事', 'sort' => 60, 'data_scope' => 'all', 'description' => '负责员工档案、试用期、绩效和工资流程'],
            ['alias' => 'telesales', 'name' => '电销', 'sort' => 55, 'data_scope' => 'self', 'description' => '负责电话触达、需求激活、诊断会预约和跟进'],
        ];

        foreach ($roles as $role) {
            $existing = DB::table('roles')->where('alias', $role['alias'])->first();
            if ($existing) {
                DB::table('roles')->where('id', $existing->id)->update([
                    'sort' => $role['sort'],
                    'data_scope' => $role['data_scope'],
                    'description' => $role['description'],
                    'status' => 1,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('roles')->insert([
                'name' => $role['name'],
                'guard_name' => 'admin',
                'alias' => $role['alias'],
                'sort' => $role['sort'],
                'data_scope' => $role['data_scope'],
                'description' => $role['description'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissions = [
            ['dashboard.view', '经营总览查看', 'dashboard', 'menu', '/dashboard', '查看公司经营总览和本人权限范围内指标'],
            ['dashboard.export', '经营总览导出', 'dashboard', 'button', null, '导出经营看板统计数据'],
            ['sales.customer.view', '客户资料查看', 'sales', 'menu', '/sales/customers', '查看客户、联系人和客户阶段'],
            ['sales.customer.manage', '客户资料管理', 'sales', 'button', null, '新增、编辑、归属和协作客户'],
            ['sales.order.view', '销售订单查看', 'sales', 'menu', '/sales/orders', '查看销售订单与阶段记录'],
            ['sales.order.create', '销售订单创建', 'sales', 'button', null, '创建客户订单并提交资料'],
            ['sales.order.update', '销售订单修改', 'sales', 'button', null, '修改草稿或退回补充的订单'],
            ['sales.order.submit', '销售订单提交', 'sales', 'button', null, '提交销售总监与财务审核'],
            ['sales.order.approve', '销售订单审核', 'sales', 'button', null, '审核客户归属、报价、风险和完整性'],
            ['sales.order.assign', '销售订单分配', 'sales', 'button', null, '分配销售、技术总监及交付成员'],
            ['sales.followup.manage', '客户跟进管理', 'sales', 'button', null, '登记跟进结果、下一步和截止时间'],
            ['delivery.project.view', '交付项目查看', 'delivery', 'menu', '/delivery/projects', '查看交付项目、节点、任务和风险'],
            ['delivery.project.assign', '交付项目分单', 'delivery', 'button', null, '分配优化师、助理和调整负责人'],
            ['delivery.project.manage', '交付项目管理', 'delivery', 'button', null, '更新成功标准、基线、节点和风险'],
            ['delivery.task.manage', '交付任务管理', 'delivery', 'button', null, '创建、执行和更新交付任务'],
            ['delivery.evidence.submit', '交付凭证提交', 'delivery', 'button', null, '提交任务链接、截图、文件和版本'],
            ['delivery.quality.review', '交付质量审核', 'delivery', 'button', null, '审核任务凭证、退回和发布确认'],
            ['finance.view', '财务看板查看', 'finance', 'menu', '/finance', '查看到账、应收、票据、退款和风险'],
            ['finance.payment.confirm', '到账确认', 'finance', 'button', null, '核验付款主体、金额、公司账户和合同'],
            ['finance.receivable.manage', '应收跟进管理', 'finance', 'button', null, '维护分期计划、到期提醒和跟进人'],
            ['finance.invoice.manage', '发票管理', 'finance', 'button', null, '处理发票申请、开具和作废'],
            ['finance.refund.manage', '退款管理', 'finance', 'button', null, '处理退款、冲回及人事通知'],
            ['finance.export', '财务对账导出', 'finance', 'button', null, '按客户、销售、产品和月份导出对账数据'],
            ['hr.employee.view', '员工档案查看', 'hr', 'menu', '/hr/employees', '按权限范围查看员工档案'],
            ['hr.employee.manage', '员工档案管理', 'hr', 'button', null, '维护员工、部门、岗位、状态和合同信息'],
            ['hr.probation.manage', '试用期管理', 'hr', 'button', null, '维护试用期节点、预警、评估和转正结果'],
            ['hr.performance.manage', '绩效管理', 'hr', 'button', null, '维护绩效方案、结果、确认和申诉'],
            ['hr.payroll.view', '工资明细查看', 'hr', 'menu', '/hr/payroll', '查看权限范围内工资表与明细'],
            ['hr.payroll.manage', '工资核算管理', 'hr', 'button', null, '计算、复核、审批和锁定工资表'],
            ['crm.dashboard.view', '电销看板查看', 'crm', 'menu', '/crm', '查看拨打、接通、有效沟通和转化数据'],
            ['crm.call.create', '发起电销呼叫', 'crm', 'button', null, '调用供应商接口并创建通话记录'],
            ['crm.followup.manage', '电销跟进管理', 'crm', 'button', null, '维护通话结果、客户阶段和下次跟进'],
            ['crm.quality.review', '电销质检', 'crm', 'button', null, '进行录音抽检、质检标签和违规预警'],
            ['system.user.manage', '系统用户管理', 'system', 'menu', '/system/users', '管理用户账号、状态和角色'],
            ['system.role.manage', '系统角色管理', 'system', 'menu', '/system/roles', '管理角色、数据范围和状态'],
            ['system.permission.manage', '系统权限管理', 'system', 'menu', '/system/permissions', '管理权限菜单、按钮和接口定义'],
            ['system.audit.view', '操作日志查看', 'system', 'menu', '/system/audit', '查看关键业务操作审计日志'],
        ];

        foreach ($permissions as $sort => $permission) {
            [$name, $alias, $module, $type, $route, $description] = $permission;
            DB::table('permissions')->updateOrInsert(
                ['name' => $name, 'guard_name' => 'admin'],
                [
                    'alias' => $alias,
                    'module' => $module,
                    'permission_type' => $type,
                    'route_path' => $route,
                    'sort' => 1000 - $sort,
                    'status' => 1,
                    'description' => $description,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $rolePermissions = [
            'sales_director' => ['dashboard.view', 'dashboard.export', 'sales.customer.view', 'sales.customer.manage', 'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.submit', 'sales.order.approve', 'sales.order.assign', 'sales.followup.manage', 'finance.view', 'crm.dashboard.view', 'crm.quality.review'],
            'sales_manager' => ['dashboard.view', 'sales.customer.view', 'sales.customer.manage', 'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.submit', 'sales.order.approve', 'sales.order.assign', 'sales.followup.manage', 'crm.dashboard.view', 'crm.followup.manage'],
            'sales' => ['dashboard.view', 'sales.customer.view', 'sales.customer.manage', 'sales.order.view', 'sales.order.create', 'sales.order.update', 'sales.order.submit', 'sales.followup.manage', 'finance.view'],
            'technical_director' => ['dashboard.view', 'sales.customer.view', 'sales.order.view', 'delivery.project.view', 'delivery.project.assign', 'delivery.project.manage', 'delivery.task.manage', 'delivery.evidence.submit', 'delivery.quality.review', 'finance.view'],
            'optimizer' => ['dashboard.view', 'sales.customer.view', 'sales.order.view', 'delivery.project.view', 'delivery.project.manage', 'delivery.task.manage', 'delivery.evidence.submit', 'sales.followup.manage'],
            'assistant' => ['dashboard.view', 'sales.customer.view', 'delivery.project.view', 'delivery.task.manage', 'delivery.evidence.submit'],
            'finance' => ['dashboard.view', 'sales.customer.view', 'sales.order.view', 'finance.view', 'finance.payment.confirm', 'finance.receivable.manage', 'finance.invoice.manage', 'finance.refund.manage', 'finance.export', 'hr.payroll.view'],
            'hr' => ['dashboard.view', 'hr.employee.view', 'hr.employee.manage', 'hr.probation.manage', 'hr.performance.manage', 'hr.payroll.view', 'hr.payroll.manage', 'system.audit.view'],
            'telesales' => ['dashboard.view', 'sales.customer.view', 'sales.customer.manage', 'sales.followup.manage', 'crm.dashboard.view', 'crm.call.create', 'crm.followup.manage'],
        ];

        foreach ($rolePermissions as $roleAlias => $permissionNames) {
            $roleId = DB::table('roles')->where('alias', $roleAlias)->value('id');
            if (!$roleId) {
                continue;
            }

            $permissionIds = DB::table('permissions')
                ->where('guard_name', 'admin')
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // 超级管理员获得当前库内所有权限，包括旧系统兼容权限。
        $adminRoleId = DB::table('roles')->where('alias', 'admin')->value('id');
        if ($adminRoleId) {
            foreach (DB::table('permissions')->where('guard_name', 'admin')->pluck('id') as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }

        // 清理 Spatie 权限缓存，确保迁移完成后立即生效。
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // 权限为生产基础数据，回滚结构时不自动删除，避免误伤现有授权。
    }
};
