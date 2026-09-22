<?php

namespace App\Console\Commands;

use App\Http\Model\WeiwenjiaUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncWeiwenjiaExternalUsers extends Command
{
    protected $signature = 'crm:sync-weiwenjia-users {--page-size=100}';
    protected $description = '同步微问家第三方成员到独立CRM成员表';

    public function handle(): int
    {
        $base = rtrim(env('WEIWENJIA_THIRD_BASE_URL', 'https://external-connection.weiwenjia.com'), '/');
        $login = Http::timeout(30)->post(rtrim(env('WEIWENJIA_CRM_BASE_URL', 'https://lxcrm.weiwenjia.com'), '/') . '/api/v2/auth/login', [
            'device' => 'open_api', 'login' => env('WEIWENJIA_CRM_LOGIN'), 'password' => env('WEIWENJIA_CRM_PASSWORD'),
        ]);
        $loginData = $login->json('data', []);
        $token = $loginData['crm_app_token'] ?? $loginData['user_token'] ?? null;
        if (! $login->successful() || ! $token) {
            $this->error('微问家登录失败，无法获取第三方接口 token');
            return self::FAILURE;
        }
        $headers = ['Content-Type' => 'application/json', 'Access-Token' => $token, 'Company-Code' => env('WEIWENJIA_COMPANY_CODE', $loginData['corp_id'] ?? '')];
        $operatorId = env('WEIWENJIA_OPERATOR_ID', $loginData['uid'] ?? $loginData['user_id'] ?? '');
        $page = 1;
        $size = max(1, (int) $this->option('page-size'));
        $total = 0;
        do {
            $response = Http::timeout(60)->withHeaders($headers)->post($base . '/third_api/users/list', ['operator_id' => $operatorId, 'page' => $page, 'per_page' => $size]);
            if (! $response->successful() || (int) $response->json('code', 1) !== 0) {
                $this->error('微问家成员列表请求失败，页码：' . $page);
                return self::FAILURE;
            }
            $data = $response->json('data', []);
            $rows = $data['list'] ?? [];
            foreach ($rows as $row) {
                $id = (string) ($row['id'] ?? '');
                if ($id === '') continue;
                WeiwenjiaUser::updateOrCreate(['external_id' => $id], ['name' => $row['name'] ?? '', 'department_name' => $row['departmentName'] ?? $row['department_name'] ?? null, 'status' => $row['status'] ?? null, 'raw_payload' => $row, 'external_updated_at' => $row['updatedAt'] ?? null, 'synced_at' => now()]);
                $total++;
            }
            $hasMore = (bool) ($data['has_next_page'] ?? ($page < (int) ($data['total_pages'] ?? $page)));
            $page++;
        } while ($hasMore);
        $this->info("同步完成，共处理 {$total} 名第三方成员");
        return self::SUCCESS;
    }
}
