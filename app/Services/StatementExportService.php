<?php

namespace App\Services;

use App\Models\Customer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class StatementExportService
{
    public function xlsx(Customer $customer, string $currency, array $statement, ?string $from, string $to): string
    {
        if (! class_exists(Spreadsheet::class)) {
            throw new RuntimeException('مكتبة PhpSpreadsheet غير مثبتة. شغّل composer install أولًا.');
        }

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('كشف الحساب');
        $sheet->setRightToLeft(true);

        $sheet->fromArray([
            ['كشف حساب العميل', $customer->name],
            ['كود العميل', $customer->code],
            ['العملة', $currency],
            ['الفترة', ($from ?: 'من بداية التعامل').' إلى '.$to],
            ['الرصيد الافتتاحي', (float) $statement['opening']],
        ], null, 'A1');

        $headerRow = 7;
        $sheet->fromArray([['التاريخ','نوع الحركة','رقم المرجع','البيان','مدين','دائن','الرصيد']], null, 'A'.$headerRow);
        $row = $headerRow + 1;
        foreach ($statement['rows'] as $statementRow) {
            $entry = $statementRow['entry'];
            $sheet->fromArray([[
                $entry->entry_date->format('Y-m-d'),
                $statementRow['label'],
                $entry->reference_no,
                $entry->description,
                (float) $entry->debit,
                (float) $entry->credit,
                (float) $statementRow['balance'],
            ]], null, 'A'.$row++);
        }

        $sheet->fromArray([
            ['الإجماليات','','','', (float) $statement['debit'], (float) $statement['credit'], (float) $statement['closing']],
        ], null, 'A'.$row);

        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:G{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("E8:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A1:G{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'raito-statement-');
        if ($path === false) {
            throw new RuntimeException('تعذر إنشاء ملف كشف الحساب.');
        }
        $xlsxPath = $path.'.xlsx';
        @unlink($path);
        (new Xlsx($book))->save($xlsxPath);
        $book->disconnectWorksheets();

        return $xlsxPath;
    }
}
