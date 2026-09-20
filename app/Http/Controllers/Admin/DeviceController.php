<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Model\Device;
use App\Http\Model\DeviceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(private Request $request) {}

    public function list()
    {
        $page = $this->request->input('page', 1);
        $pageSize = $this->request->input('pageSize', 10);
        $device = Device::query();

        if ($this->request->filled('id')) {
            $device->where('id', $this->request->input('id'));
        }
        if ($this->request->filled('device_number')) {
            $device->where('device_number', 'like', '%' . $this->request->input('device_number') . '%');
        }
        if ($this->request->filled('model')) {
            $device->where('model', 'like', '%' . $this->request->input('model') . '%');
        }
        if ($this->request->filled('color')) {
            $device->where('color', 'like', '%' . $this->request->input('color') . '%');
        }
        if ($this->request->filled('memory')) {
            $device->where('memory', 'like', '%' . $this->request->input('memory') . '%');
        }
        if ($this->request->filled('purchase_date')) {
            $device->whereDate('purchase_date', $this->request->input('purchase_date'));
        }
        if ($this->request->filled('purchase_channel')) {
            $device->where('purchase_channel', 'like', '%' . $this->request->input('purchase_channel') . '%');
        }
        if ($this->request->filled('price')) {
            $device->where('price', $this->request->input('price'));
        }
        if ($this->request->filled('holder')) {
            $device->where('holder', 'like', '%' . $this->request->input('holder') . '%');
        }
        if ($this->request->filled('status')) {
            $device->where('status', $this->request->input('status'));
        }

        return $device->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function detail()
    {
        $data = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new Device)->getTable(), 'id')],
        ]);

        return Device::findOrFail($data['id']);
    }

    public function add()
    {
        return Device::create($this->validatedData());
    }

    public function update()
    {
        $id = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new Device)->getTable(), 'id')],
        ])['id'];

        return DB::transaction(function () use ($id) {
            $device = Device::findOrFail($id);
            $device->fill($this->validatedData($device->id));
            $changes = $device->getDirty();
            $original = $device->getOriginal();
            $device->save();

            $this->createUpdateLogs($device->id, $changes, $original);

            return $device->refresh();
        });
    }

    public function logList()
    {
        $data = $this->request->validate([
            'device_id' => ['required', 'integer', Rule::exists((new Device)->getTable(), 'id')],
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return DeviceLog::where('device_id', $data['device_id'])
            ->orderByDesc('created_at')
            ->paginate($data['pageSize'] ?? 10, ['*'], 'page', $data['page'] ?? 1);
    }

    public function delete()
    {
        $data = $this->request->validate([
            'id' => ['required', 'integer', Rule::exists((new Device)->getTable(), 'id')],
        ]);

        return Device::whereKey($data['id'])->delete();
    }

    private function validatedData(?int $id = null): array
    {
        $table = (new Device)->getTable();
        $required = $id === null ? 'required' : 'sometimes';

        return $this->request->validate([
            'device_number' => [$required, 'string', 'max:255', Rule::unique($table, 'device_number')->ignore($id)],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'memory' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_channel' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'holder' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([
                Device::STATUS_IN_USE,
                Device::STATUS_PENDING,
                Device::STATUS_REPAIRING,
                Device::STATUS_OUTBOUND,
            ])],
        ]);
    }

    private function createUpdateLogs(int $deviceId, array $changes, array $original): void
    {
        $fieldNames = [
            'device_number' => '设备编号',
            'model' => '型号',
            'color' => '颜色',
            'memory' => '内存',
            'purchase_date' => '购买日期',
            'purchase_channel' => '购买渠道',
            'price' => '价格',
            'holder' => '持有人',
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

        DeviceLog::create([
            'device_id' => $deviceId,
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
                Device::STATUS_IN_USE => '使用',
                Device::STATUS_PENDING => '待定',
                Device::STATUS_REPAIRING => '维修',
                Device::STATUS_OUTBOUND => '出库',
            ][$value] ?? (string) $value;
        }

        return (string) $value;
    }
}
