<?php

namespace App\Console\Commands;

use App\Http\Model\WeiwenjiaUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncWeiwenjiaExternalUsers extends Command
{
    protected $signature = 'crm:sync-weiwenjia-users {--page-size=100}';
    protected $description = '同步微问家 CRM 用户简表到独立 CRM 成员表';

    public function handle(): int
    {
        $base = rtrim(env('WEIWENJIA_CRM_BASE_URL', 'https://lxcrm.weiwenjia.com'), '/');
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
        ];
        $page = 1;
        $size = max(1, (int) $this->option('page-size'));
        $total = 0;
        do {
            $response = Http::timeout(60)->withHeaders($headers)->get($base . '/api/v2/user/simple_list', [
                'page' => $page,
                'per_page' => $size,
            ]);
            $responseCode = $response->json('code');
            if (! $response->successful() || ($responseCode !== null && (int) $responseCode !== 0)) {
                $this->error('微问家用户简表请求失败，页码：' . $page . '，HTTP ' . $response->status() . '，接口 code=' . (string) $response->json('code', ''));
                return self::FAILURE;
            }
            $payload = $response->json();
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $rows = $data['options'] ?? ($payload['simple_users'] ?? null);
            if (! is_array($rows)) {
                $this->error('微问家用户简表响应结构异常：' . mb_substr(strip_tags($response->body()), 0, 500));
                return self::FAILURE;
            }
            foreach ($rows as $row) {
                $id = (string) ($row['value'] ?? '');
                if ($id === '') continue;
                WeiwenjiaUser::updateOrCreate(['external_id' => $id], [
                    'name' => $row['label'] ?? '',
                    'department_name' => $row['department_name'] ?? null,
                    'raw_payload' => $row,
                    'synced_at' => now(),
                ]);
                $total++;
            }
            $currentPage = max(1, (int) ($data['page'] ?? $page));
            $perPage = max(1, (int) ($data['per_page'] ?? $size));
            $totalCount = max(0, (int) ($data['total_count'] ?? 0));
            $hasMore = $currentPage * $perPage < $totalCount;
            $page++;
        } while ($hasMore);
        $this->info("同步完成，共处理 {$total} 名微问家 CRM 用户");
        return self::SUCCESS;
    }
}
