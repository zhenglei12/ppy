<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Model\OperationAccount;
use App\Http\Model\OperationAccountLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationAccountController extends Controller
{
    public function __construct(private Request $request) {}

    public function list()
    {
        $page = $this->request->input('page', 1);
        $pageSize = $this->request->input('pageSize', 10);
        $account = OperationAccount::query();

        if ($this->request->filled('id')) {
            $account->where('id', $this->request->input('id'));
        }
        if ($this->request->filled('platform')) {
            $account->where('platform', 'like', '%' . $this->request->input('platform') . '%');
        }
        if ($this->request->filled('name')) {
            $account->where('name', 'like', '%' . $this->request->input('name') . '%');
        }
        if ($this->request->filled('platform_account_id')) {
            $account->where('platform_account_id', 'like', '%' . $this->request->input('platform_account_id') . '%');
        }
        if ($this->request->filled('device_carrier')) {
            $account->where('device_carrier', 'like', '%' . $this->request->input('device_carrier') . '%');
        }
        if ($this->request->filled('bound_phone_card')) {
            $account->where('bound_phone_card', 'like', '%' . $this->request->input('bound_phone_card') . '%');
        }
        if ($this->request->filled('phone_card_owner')) {
            $account->where('phone_card_owner', 'like', '%' . $this->request->input('phone_card_owner') . '%');
        }
        if ($this->request->filled('other_information')) {
            $account->where('other_information', 'like', '%' . $this->request->input('other_information') . '%');
        }
        if ($this->request->filled('person_in_charge')) {
            $account->where('person_in_charge', 'like', '%' . $this->request->input('person_in_charge') . '%');
        }
        if ($this->request->filled('status')) {
            $account->where('status', $this->request->input('status'));
        }

        return $account->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function detail()
    {
        $data = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new OperationAccount)->getTable(), 'id')],
        ]);

        return OperationAccount::findOrFail($data['id']);
    }

    public function add()
    {
        return OperationAccount::create($this->validatedData());
    }

    public function update()
    {
        $id = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new OperationAccount)->getTable(), 'id')],
        ])['id'];

        return DB::transaction(function () use ($id) {
            $account = OperationAccount::findOrFail($id);
            $account->fill($this->validatedData($account->id));
            $changes = $account->getDirty();
            $original = $account->getOriginal();
            $account->save();

            $this->createUpdateLogs($account->id, $changes, $original);

            return $account->refresh();
        });
    }

    public function logList()
    {
        $data = $this->request->validate([
            'operation_account_id' => ['required', 'integer', Rule::exists((new OperationAccount)->getTable(), 'id')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return OperationAccountLog::where('operation_account_id', $data['operation_account_id'])
            ->orderByDesc('created_at')
            ->paginate($data['pageSize'] ?? 10, ['*'], 'page', $data['page'] ?? 1);
    }

    public function delete()
    {
        $data = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new OperationAccount)->getTable(), 'id')],
        ]);

        return OperationAccount::whereKey($data['id'])->delete();
    }

    private function validatedData(?int $id = null): array
    {
        $required = $id === null ? 'required' : 'sometimes';

        return $this->request->validate([
            'platform' => [$required, 'string', 'max:255'],
            'name' => [$required, 'string', 'max:255'],
            'platform_account_id' => [
                $required,
                'string',
                'max:255',
                Rule::unique((new OperationAccount)->getTable(), 'platform_account_id')
                    ->where(fn($query) => $query->where('platform', $this->request->input('platform')))
                    ->ignore($id),
            ],
            'device_carrier' => ['nullable', 'string', 'max:255'],
            'bound_phone_card' => ['nullable', 'string', 'max:255'],
            'phone_card_owner' => ['nullable', 'string', 'max:255'],
            'other_information' => ['nullable', 'string'],
            'person_in_charge' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([
                OperationAccount::STATUS_ENABLED,
                OperationAccount::STATUS_DISABLED,
                OperationAccount::STATUS_FROZEN,
            ])],
        ]);
    }

    private function createUpdateLogs(int $accountId, array $changes, array $original): void
    {
        $fieldNames = [
            'platform' => '运营平台',
            'name' => '名称',
            'platform_account_id' => 'ID',
            'device_carrier' => '设备载体',
            'bound_phone_card' => '绑定电话卡',
            'phone_card_owner' => '电话卡主人',
            'other_information' => '其他信息',
            'person_in_charge' => '负责人',
            'status' => '状态',
        ];
        $operator = $this->request->user()?->name ?? '未知用户';
        $changeDescriptions = [];

        foreach ($changes as $field => $newValue) {
            if (! isset($fieldNames[$field])) {
                continue;
            }

            $changeDescriptions[] = sprintf(
                '修改了%s，之前的值：%s，最新的值：%s',
                $fieldNames[$field],
                $this->displayValue($field, $original[$field] ?? null),
                $this->displayValue($field, $newValue)
            );
        }

        if ($changeDescriptions === []) {
            return;
        }

        OperationAccountLog::create([
            'operation_account_id' => $accountId,
            'content' => sprintf('操作员%s，%s', $operator, implode('；', $changeDescriptions)),
        ]);
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '空';
        }

        if ($field === 'status') {
            return [
                OperationAccount::STATUS_ENABLED => '启用',
                OperationAccount::STATUS_DISABLED => '停用',
                OperationAccount::STATUS_FROZEN => '冻结',
            ][$value] ?? (string) $value;
        }

        return (string) $value;
    }
}
