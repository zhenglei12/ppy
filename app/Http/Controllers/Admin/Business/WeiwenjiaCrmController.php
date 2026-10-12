<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\WeiwenjiaCallRecord;
use App\Http\Model\WeiwenjiaDailyStat;
use App\Http\Model\WeiwenjiaUser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class WeiwenjiaCrmController extends Controller
{
    public function dashboard(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $calls = WeiwenjiaCallRecord::query()->whereDate('called_at', $date);
        $summary = (clone $calls)->selectRaw('COUNT(*) total_calls, COALESCE(SUM(through),0) connected_calls, COALESCE(SUM(duration),0) total_duration, COUNT(DISTINCT NULLIF(external_user_id, \'\')) active_members')->first()->toArray();
        $summary['unconnected_calls'] = (int) $summary['total_calls'] - (int) $summary['connected_calls'];
        $summary['connection_rate'] = (int) $summary['total_calls'] > 0
            ? round((int) $summary['connected_calls'] * 100 / (int) $summary['total_calls'], 1)
            : 0;

        $callStats = WeiwenjiaCallRecord::query()
            ->whereDate('called_at', $date)
            ->selectRaw('external_user_id, COUNT(*) total_calls, COALESCE(SUM(through),0) connected_calls, COALESCE(SUM(duration),0) total_duration')
            ->groupBy('external_user_id');
        $members = WeiwenjiaUser::query()
            ->leftJoinSub($callStats, 'call_stats', 'call_stats.external_user_id', '=', 'crm_weiwenjia_users.external_id')
            ->selectRaw('crm_weiwenjia_users.id, crm_weiwenjia_users.external_id, crm_weiwenjia_users.name user_name, crm_weiwenjia_users.department_name, COALESCE(call_stats.total_calls,0) total_calls, COALESCE(call_stats.connected_calls,0) connected_calls, COALESCE(call_stats.total_duration,0) total_duration')
            ->orderBy('crm_weiwenjia_users.department_name')
            ->orderByDesc('total_calls')
            ->paginate($request->integer('pageSize', 50));
        $members->getCollection()->transform(function ($member) {
            $member->unconnected_calls = (int) $member->total_calls - (int) $member->connected_calls;
            $member->connection_rate = (int) $member->total_calls > 0
                ? round((int) $member->connected_calls * 100 / (int) $member->total_calls, 1)
                : 0;
            $member->average_duration = (int) $member->connected_calls > 0
                ? round((int) $member->total_duration / (int) $member->connected_calls)
                : 0;
            return $member;
        });

        return ['date' => $date, 'summary' => $summary, 'members' => $members];
    }

    public function sync(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $userExitCode = Artisan::call('crm:sync-weiwenjia-users');
        if ($userExitCode !== 0) {
            return response()->json(['message' => trim(Artisan::output()) ?: '微问家成员同步失败'], 500);
        }
        $callExitCode = Artisan::call('crm:sync-weiwenjia-call-records', ['--date' => $date, '--page-size' => 1000]);
        if ($callExitCode !== 0) {
            return response()->json(['message' => trim(Artisan::output()) ?: '微问家通话流水同步失败'], 500);
        }

        return [
            'message' => '同步完成',
            'date' => $date,
            'user_count' => WeiwenjiaUser::count(),
            'call_count' => WeiwenjiaCallRecord::whereDate('called_at', $date)->count(),
        ];
    }

    public function callRecords(Request $request)
    {
        $records = WeiwenjiaCallRecord::query()->when($request->filled('date'), fn ($q) => $q->whereDate('called_at', $request->input('date')))->latest('called_at')->paginate($request->integer('pageSize', 20));
        return $records;
    }

    public function ingest(Request $request)
    {
        $payload = $request->all();
        $rows = $payload['callRecords'] ?? $payload['records'] ?? ($payload['data']['callRecords'] ?? []);
        if (! is_array($rows)) return response()->json(['message' => 'callRecords must be an array'], 422);
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') continue;
            WeiwenjiaCallRecord::updateOrCreate(['external_id' => $id], [
                'external_user_id' => (string) ($row['thirdUserId'] ?? $row['userId'] ?? ''),
                'external_customer_id' => (string) ($row['thirdCustomerId'] ?? $row['customerId'] ?? ''),
                'phone' => $row['phone'] ?? null, 'through' => (bool) ($row['through'] ?? false),
                'duration' => (int) ($row['duration'] ?? 0), 'tip_type' => $row['tipTypeString'] ?? null,
                'tip_name' => $row['tipName'] ?? null, 'called_at' => $row['createTime'] ?? null, 'raw_payload' => $row,
            ]);
        }
        return ['received' => count($rows)];
    }

    public function upsertDaily(Request $request)
    {
        $rows = $request->input('rows', []);
        if (! is_array($rows)) return response()->json(['message' => 'rows must be an array'], 422);
        foreach ($rows as $row) {
            $externalId = (string) ($row['external_user_id'] ?? $row['thirdUserId'] ?? '');
            if ($externalId === '') continue;
            WeiwenjiaDailyStat::updateOrCreate(['report_date' => $row['report_date'] ?? now()->toDateString(), 'external_user_id' => $externalId], array_merge($row, ['external_user_id' => $externalId, 'raw_payload' => $row]));
        }
        return ['received' => count($rows)];
    }
}
