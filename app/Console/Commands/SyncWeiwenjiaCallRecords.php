<?php

namespace App\Console\Commands;

use App\Http\Model\WeiwenjiaCallRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class SyncWeiwenjiaCallRecords extends Command
{
    protected $signature = 'crm:sync-weiwenjia-call-records {--date=} {--page-size=1000}';
    protected $description = '同步微问家电销通话流水';

    public function handle(): int
    {
        $base = rtrim(env('WEIWENJIA_CRM_BASE_URL', 'https://lxcrm.weiwenjia.com'), '/');
        $date = Carbon::parse($this->option('date') ?: now()->toDateString());
        $login = Http::timeout(30)->post($base . '/api/v2/auth/login', [
            'device' => 'open_api',
            'login' => env('WEIWENJIA_CRM_LOGIN'),
            'password' => env('WEIWENJIA_CRM_PASSWORD'),
        ]);
        $loginData = $login->json('data', []);
        $token = $loginData['user_token'] ?? $loginData['crm_app_token'] ?? null;
        if (! $login->successful() || ! $token) {
            $this->error('微问家登录失败，无法获取用户 token');
            return self::FAILURE;
        }

        $headers = [
            'Authorization' => 'Token token=' . $token . ', device=open_api, version_code=9.9.9',
            'access-token' => $token,
            'X-LXY-PLATFORM' => 'lixiaoyun',
            'X-LXY-APP' => 'crm',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        $page = 1;
        $size = max(1, (int) $this->option('page-size'));
        $total = 0;
        do {
            $response = Http::timeout(60)->withHeaders($headers)->post($base . '/api/open/dx/v1/router/index', [
                'type' => 'wwj.dx.call.record.list.get',
                'data' => [
                    'callTimeType' => 7,
                    'callType' => 0,
                    'callTimeLengthType' => 0,
                    'effectiveType' => 0,
                    'connectedType' => 0,
                    'callSource' => 0,
                    'partId' => '',
                    'userId' => '',
                    'callMode' => 0,
                    'numberType' => 'all',
                    'pageSize' => $size,
                    'pageNum' => $page,
                    'startDate' => $date->copy()->startOfDay()->getTimestampMs(),
                    'endDate' => $date->copy()->endOfDay()->getTimestampMs(),
                ],
            ]);
            $errorCode = $response->json('error_code');
            if (! $response->successful() || ($errorCode !== null && (int) $errorCode !== 0)) {
                $this->error('微问家电销流水请求失败，页码：' . $page . '，HTTP ' . $response->status() . '，接口 code=' . (string) $errorCode);
                return self::FAILURE;
            }
            $data = $response->json('data', []);
            $rows = $data['list'] ?? [];
            if (! is_array($rows)) {
                $this->error('微问家电销流水响应结构异常');
                return self::FAILURE;
            }
            foreach ($rows as $row) {
                $id = (string) ($row['callId'] ?? $row['id'] ?? '');
                if ($id === '') continue;
                WeiwenjiaCallRecord::updateOrCreate(['external_id' => $id], [
                    'external_user_id' => (string) ($row['userId'] ?? ''),
                    'external_customer_id' => (string) ($row['recordableUserId'] ?? ''),
                    'phone' => $row['calledNumber'] ?? null,
                    'through' => (bool) ($row['isConnected'] ?? false),
                    'duration' => (int) ($row['talkTime'] ?? 0),
                    'tip_type' => $row['recordableRelationType'] ?? $row['recordableType'] ?? null,
                    'tip_name' => $row['recordableRelationName'] ?? $row['calledName'] ?? null,
                    'called_at' => $row['createdAt'] ?? null,
                    'raw_payload' => $row,
                ]);
                $total++;
            }
            $remoteTotal = max(0, (int) ($data['total'] ?? 0));
            $page++;
        } while (($page - 1) * $size < $remoteTotal && count($rows) > 0);

        $this->info("同步完成，共处理 {$total} 条微问家电销流水");
        return self::SUCCESS;
    }
}
