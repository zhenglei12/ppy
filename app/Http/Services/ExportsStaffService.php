<?php


namespace App\Http\Services;


use App\Http\Constants\BaseConstants;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportsStaffService implements FromCollection, WithHeadings, WithStyles
{
    use Exportable;

    private $data;

    public function __construct($result)
    {
        $this->setData($result);
    }

    public function headings(): array
    {
        return ["客服名称", "总金额", "已回收金额", "总尾款金额", "总售后金额"];
    }


    public function collection()
    {
        return collect($this->data);
    }

    public function styles(Worksheet $sheet)
    {
    }

    public function defaultStyle(Worksheet $sheet)
    {
        $sheet->getDefaultRowDimension()->setRowHeight(35);//设置默认行高
        $sheet->getDefaultColumnDimension()->setWidth(12);//设置默认的
        $sheet->getStyle('A1:H' . $this->row)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:H' . $this->row)->getAlignment()->setVertical('center');//设置第一行垂直居中
        $sheet->getStyle("A1:H" . $this->row)->getAlignment()->setHorizontal('center');//设置垂直居中
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
        Log::debug("111", ['aa' => $result]);
        foreach ($result as $key => $v) {
            array_push($this->data, [
                $v['staff_name'],
                $v['amount'],
                $v['received_amount'],
                $v['receipt_time'],
                $v['after_banlace'],
            ]);
        }
        Log::debug("222", ['bb' => $this->data]);

    }
}
