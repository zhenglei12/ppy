<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $fallbackCreatorId = DB::table('users')->orderBy('id')->value('id');
        if (! $fallbackCreatorId) {
            return;
        }

        DB::table('order')
            ->whereNotNull('optimizer_id')
            ->whereIn('current_stage', ['service', 'renewal', 'completed'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($orders) use ($fallbackCreatorId) {
                foreach ($orders as $order) {
                    if (DB::table('delivery_projects')->where('order_id', $order->id)->exists()) {
                        continue;
                    }

                    $now = now();
                    $projectId = DB::table('delivery_projects')->insertGetId([
                        'project_no' => 'PRJORD'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                        'order_id' => $order->id,
                        'technical_director_id' => $order->technical_director_id,
                        'optimizer_id' => $order->optimizer_id,
                        'planned_start_date' => $order->expected_start_date,
                        'actual_start_date' => $order->actual_start_date,
                        'planned_end_date' => $order->expected_end_date,
                        'current_node' => 'D1',
                        'status' => $order->current_stage === 'completed' ? 'completed' : 'active',
                        'health_status' => $order->health_status ?: 'green',
                        'created_by' => $order->created_by ?: $fallbackCreatorId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $start = $order->expected_start_date ? \Carbon\Carbon::parse($order->expected_start_date) : $now->copy();
                    $milestones = [];
                    foreach ([['D1', '成功标准确认', 1], ['D3', '成功标准完成', 2], ['D7', '事实与基线', 3], ['D15', '方向确认', 4], ['D30', '价值复盘', 5], ['D60', '续费预警', 6], ['D90', '报价与到账', 7]] as [$code, $name, $sequence]) {
                        $milestones[] = [
                            'project_id' => $projectId,
                            'milestone_code' => $code,
                            'milestone_name' => $name,
                            'sequence_no' => $sequence,
                            'planned_at' => $start->copy()->addDays((int) substr($code, 1)),
                            'status' => 'pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    DB::table('delivery_milestones')->insert($milestones);
                }
            }, 'id');
    }

    public function down(): void
    {
        // 历史交付项目属于有效业务数据，回滚时不自动删除。
    }
};
