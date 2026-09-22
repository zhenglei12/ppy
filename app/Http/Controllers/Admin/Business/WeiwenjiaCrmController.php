<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Http\Model\WeiwenjiaCallRecord;
use App\Http\Model\WeiwenjiaDailyStat;
use App\Http\Model\WeiwenjiaUser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WeiwenjiaCrmController extends Controller
{
    public function dashboard(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();
        $query = WeiwenjiaDailyStat::query()->whereDate('report_date', $date);
        $stats = (clone $query)->selectRaw('COALESCE(SUM(effective_calls),0) effective_calls, COALESCE(SUM(effective_communications),0) effective_communications, COALESCE(SUM(wechat_adds),0) wechat_adds, COALESCE(SUM(effective_dialogues),0) effective_dialogues, COALESCE(SUM(effective_activations),0) effective_activations, COALESCE(SUM(daily_moments),0) daily_moments, COALESCE(SUM(ai_reports),0) ai_reports, COALESCE(SUM(appointments),0) appointments, COALESCE(SUM(accompany_visits),0) accompany_visits, COALESCE(SUM(today_deals),0) today_deals')->first();
        return ['date' => $date, 'summary' => $stats, 'members' => $query->orderBy('department_name')->orderByDesc('effective_calls')->paginate($request->integer('pageSize', 50))];
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
