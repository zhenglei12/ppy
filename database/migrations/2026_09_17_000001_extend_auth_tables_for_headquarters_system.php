<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'employee_no')) {
                $table->string('employee_no', 64)->nullable()->unique()->comment('员工编号');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 32)->nullable()->index()->comment('手机号码');
            }
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->index()->comment('所属部门ID');
            }
            if (!Schema::hasColumn('users', 'position_name')) {
                $table->string('position_name', 100)->nullable()->comment('岗位名称');
            }
            if (!Schema::hasColumn('users', 'direct_manager_id')) {
                $table->unsignedBigInteger('direct_manager_id')->nullable()->index()->comment('直属主管用户ID');
            }
            if (!Schema::hasColumn('users', 'employment_status')) {
                $table->string('employment_status', 32)->default('active')->index()->comment('任职状态：probation试用、active在职、transferred调岗、resigned离职');
            }
            if (!Schema::hasColumn('users', 'hire_date')) {
                $table->date('hire_date')->nullable()->comment('入职日期');
            }
            if (!Schema::hasColumn('users', 'probation_end_date')) {
                $table->date('probation_end_date')->nullable()->index()->comment('试用期截止日期');
            }
            if (!Schema::hasColumn('users', 'regular_date')) {
                $table->date('regular_date')->nullable()->comment('转正日期');
            }
            if (!Schema::hasColumn('users', 'resign_date')) {
                $table->date('resign_date')->nullable()->comment('离职日期');
            }
            if (!Schema::hasColumn('users', 'base_salary')) {
                $table->decimal('base_salary', 18, 2)->default(0)->comment('基本工资');
            }
            if (!Schema::hasColumn('users', 'salary_plan_version')) {
                $table->string('salary_plan_version', 32)->nullable()->comment('薪资方案版本');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->unsignedTinyInteger('status')->default(1)->index()->comment('账号状态：0禁用、1正常、2锁定');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->dateTime('last_login_at')->nullable()->comment('最近登录时间');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->comment('逻辑删除时间');
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'sort')) {
                $table->integer('sort')->default(0)->comment('显示排序，数值越大越靠前');
            }
            if (!Schema::hasColumn('roles', 'data_scope')) {
                $table->string('data_scope', 32)->default('self')->index()->comment('数据范围：all全部、department部门、team团队、self本人、custom自定义');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->string('description', 500)->nullable()->comment('角色说明');
            }
            if (!Schema::hasColumn('roles', 'status')) {
                $table->unsignedTinyInteger('status')->default(1)->index()->comment('角色状态：0禁用、1启用');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->index()->comment('父权限ID');
            }
            if (!Schema::hasColumn('permissions', 'module')) {
                $table->string('module', 64)->nullable()->index()->comment('所属业务模块');
            }
            if (!Schema::hasColumn('permissions', 'permission_type')) {
                $table->string('permission_type', 16)->default('api')->comment('权限类型：menu菜单、button按钮、api接口、data数据');
            }
            if (!Schema::hasColumn('permissions', 'route_path')) {
                $table->string('route_path', 255)->nullable()->comment('前端路由或接口路径');
            }
            if (!Schema::hasColumn('permissions', 'sort')) {
                $table->integer('sort')->default(0)->comment('显示排序');
            }
            if (!Schema::hasColumn('permissions', 'status')) {
                $table->unsignedTinyInteger('status')->default(1)->index()->comment('权限状态：0禁用、1启用');
            }
            if (!Schema::hasColumn('permissions', 'description')) {
                $table->string('description', 500)->nullable()->comment('权限说明');
            }
        });

        // 既有基础字段也统一补充中文注释，保持字段类型、索引和业务数据不变。
        DB::statement(<<<'SQL'
ALTER TABLE `users`
    MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户主键',
    MODIFY `name` VARCHAR(255) NOT NULL COMMENT '用户姓名',
    MODIFY `email` VARCHAR(255) NOT NULL COMMENT '登录邮箱',
    MODIFY `email_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT '邮箱验证时间',
    MODIFY `password` VARCHAR(255) NOT NULL COMMENT '登录密码哈希',
    MODIFY `department_id` INT NULL DEFAULT NULL COMMENT '所属部门ID',
    MODIFY `remember_token` VARCHAR(100) NULL DEFAULT NULL COMMENT '记住登录令牌',
    MODIFY `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
    MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间'
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE `roles`
    MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '角色主键',
    MODIFY `name` VARCHAR(255) NOT NULL COMMENT '角色唯一标识',
    MODIFY `guard_name` VARCHAR(255) NOT NULL COMMENT '认证守卫名称',
    MODIFY `alias` VARCHAR(100) NULL DEFAULT NULL COMMENT '角色中文名称',
    MODIFY `sort` INT NULL DEFAULT NULL COMMENT '显示排序，数值越大越靠前',
    MODIFY `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
    MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间'
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE `permissions`
    MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '权限主键',
    MODIFY `name` VARCHAR(255) NOT NULL COMMENT '权限唯一标识',
    MODIFY `guard_name` VARCHAR(255) NOT NULL COMMENT '认证守卫名称',
    MODIFY `alias` VARCHAR(100) NOT NULL COMMENT '权限中文名称',
    MODIFY `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
    MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间'
SQL);

        // MySQL 不允许直接修改被外键引用的字段定义，先移除并按原规则恢复外键。
        DB::statement('ALTER TABLE `role_has_permissions` DROP FOREIGN KEY `role_has_permissions_permission_id_foreign`');
        DB::statement('ALTER TABLE `role_has_permissions` DROP FOREIGN KEY `role_has_permissions_role_id_foreign`');
        DB::statement(<<<'SQL'
ALTER TABLE `role_has_permissions`
    MODIFY `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限ID',
    MODIFY `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色ID'
SQL);
        DB::statement('ALTER TABLE `role_has_permissions` ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE');
        DB::statement('ALTER TABLE `role_has_permissions` ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE');

        // 只补充表注释，不重建 Spatie 的既有外键和复合主键。
        DB::statement("ALTER TABLE `users` COMMENT = '系统用户与员工基础档案表'");
        DB::statement("ALTER TABLE `roles` COMMENT = '系统角色表'");
        DB::statement("ALTER TABLE `permissions` COMMENT = '系统权限表'");
        DB::statement("ALTER TABLE `role_has_permissions` COMMENT = '角色与权限关联表'");
    }

    public function down(): void
    {
        // 生产库增量迁移：为保护原有用户与权限数据，不在回滚时删除扩展字段。
    }
};
