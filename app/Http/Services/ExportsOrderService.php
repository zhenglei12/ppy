<?php


namespace App\Http\Services;


use App\Http\Constants\BaseConstants;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportsOrderService implements FromCollection, WithHeadings, WithStyles
{
    use Exportable;

    private $data;

    public function __construct($result)
    {
        $this->setData($result);
    }

    public function headings(): array
    {
        return ["id", "店铺账号", "任务类型", "学历", "专业名称", "客户姓名", "客户电话", "题目", "字数", "查重", "备注信息", "创建时间", "截止时间", "相关附件", "开题报告", "稿件状态", "稿件下载", "收款账户", "订单总额", "定金截图", "尾款截图", "其他金额", "财务", "创建客服", "责任编辑",];
    }


    public function collection()
    {
        return collect($this->data);
    }

    public function styles(Worksheet $sheet) {}

    public function defaultStyle(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(35); //设置默认行高
        $sheet->getDefaultColumnDimension()->setWidth(12); //设置默认的
        $sheet->getStyle('A1:H' . $this->row)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:H' . $this->row)->getAlignment()->setVertical('center'); //设置第一行垂直居中
        $sheet->getStyle("A1:H" . $this->row)->getAlignment()->setHorizontal('center'); //设置垂直居中
        $styles = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ];
        $sheet->getStyle('A1:h' . $this->row)->applyFromArray($styles);
    }


    private function setData($result)
    {
        $this->data = [];
        foreach ($result as $key => $v) {
            array_push($this->data, [
                $v['id'],
                $v['shop_name'],
                BaseConstants::TASKTYPE[$v['task_type']],
                BaseConstants::EDUCATION[$v['education']],
                $v['major_name'],
                $v['name'],
                $v['phone'],
                $v['subject'],
                $v['word_number'],
                $v['duplicate_checking'],
                $v['remark'],
                $v['created_at'],
                $v['submission_time'],
                $v['attachment'],
                BaseConstants::ORDERSTARTLIST[$v['proposal']],
                BaseConstants::ORDERSTARTLIST[$v['status']],
                $v['manuscript'],
                $v['receipt_account_new'],
                $v['amount'],
                $v['pay_img'],
                $v['receipt_account'],
                $v['othen_amount'],
                BaseConstants::FINANCE[$v['finance_check']],
                $v['staff_name'],
                $v['edit_name'],
            ]);
        }
    }
}
