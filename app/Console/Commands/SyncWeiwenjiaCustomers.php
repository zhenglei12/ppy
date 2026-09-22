<?php

namespace App\Console\Commands;

use App\Http\Model\Contact;
use App\Http\Model\Customer;
use App\Http\Model\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncWeiwenjiaCustomers extends Command
{
    protected $signature = 'crm:sync-weiwenjia {--page-size=100}';
    protected $description = '同步微问家开放平台客户到本地CRM客户表';

    public function handle(): int
    {
        $base = rtrim(env('WEIWENJIA_CRM_BASE_URL', 'https://lxcrm.weiwenjia.com'), '/');
        $login = Http::timeout(30)->post($base.'/api/v2/auth/login', [
            'device' => 'open_api', 'login' => env('WEIWENJIA_CRM_LOGIN'), 'password' => env('WEIWENJIA_CRM_PASSWORD'),
        ]);
        $token = data_get($login->json(), 'data.user_token');
        if (! $login->successful() || ! $token) {
            $this->error('微问家登录失败');
            return self::FAILURE;
        }

        $page = 1; $total = 0; $size = max(1, (int) $this->option('page-size'));
        $ownerId = (int) env('WEIWENJIA_CRM_OWNER_USER_ID', 0) ?: User::whereHas('roles', fn ($q) => $q->where('alias', 'admin'))->value('id');
        if (! $ownerId) { $this->error('未配置 WEIWENJIA_CRM_OWNER_USER_ID，无法创建客户'); return self::FAILURE; }

        do {
            $response = Http::timeout(60)->withHeaders(['Authorization' => 'Token token='.$token.', device=open_api, version_code=9.9.9', 'X-LXY-PLATFORM' => 'lixiaoyun', 'X-LXY-APP' => 'crm', 'Accept' => 'application/json'])->get($base.'/api/v2/customers', ['page' => $page, 'per_page' => $size]);
            if (! $response->successful()) { $this->error('客户列表请求失败，页码：'.$page); return self::FAILURE; }
            $customers = data_get($response->json(), 'data.customers', []);
            foreach ($customers as $item) { $this->syncCustomer($item, $ownerId); $total++; }
            $page++; $hasMore = count($customers) >= $size;
        } while ($hasMore);

        $this->info("同步完成，共处理 {$total} 条客户");
        return self::SUCCESS;
    }

    private function syncCustomer(array $item, int $ownerId): void
    {
        $externalId = (string) ($item['id'] ?? ''); if ($externalId === '') return;
        $address = $item['address'] ?? []; $phone = $address['phone'] ?? null; $tel = $address['tel'] ?? null;
        $customer = Customer::firstOrNew(['source' => 'weiwenjia', 'external_id' => $externalId]);
        $customer->fill(['customer_no' => $customer->customer_no ?: 'WWJ'.$externalId, 'legal_name' => $item['name'] ?? '未命名客户', 'industry' => $item['category_mapped'] ?? null, 'address' => $address['detail_address'] ?? null, 'source' => 'weiwenjia', 'owner_user_id' => $customer->owner_user_id ?: $ownerId, 'status' => 'lead', 'external_status' => (string) ($item['status'] ?? ''), 'external_status_name' => $item['status_mapped'] ?? null, 'external_owner_name' => $item['user_name'] ?? null, 'external_labels' => $item['labels'] ?? [], 'external_payload' => $item, 'external_created_at' => $item['created_at'] ?? null, 'external_updated_at' => $item['updated_at'] ?? null, 'synced_at' => now()]);
        $customer->save();
        $contactName = $item['name'] ?? '客户联系人';
        if ($phone || $tel || ! $customer->contacts()->exists()) { $contact = $customer->contacts()->firstOrNew(['is_primary' => true]); $contact->fill(['contact_name' => $contactName, 'mobile' => $phone ?: $tel, 'wechat_no' => $address['wechat'] ?? null, 'is_primary' => true]); $contact->save(); }
    }
}
