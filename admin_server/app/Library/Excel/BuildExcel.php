<?php

declare(strict_types=1);

namespace App\Library\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BuildExcel extends Excel
{
    protected $excel;

    protected $sheet;

    protected $line = 1;

    protected $headsKey = [];

    protected $cellFormat = [];

    public function __construct()
    {
        $this->excel = new Spreadsheet();
        $this->sheet = $this->excel->getActiveSheet();
    }

    public function setSheetTitle(string $title)
    {
        $this->sheet->setTitle($title);

        return $this;
    }

    public function setTableHead(array $heads)
    {
        $num = 1;
        foreach ($heads as $key => $head) {
            $coordinate = Coordinate::stringFromColumnIndex($num);
            $this->sheet->setCellValue($coordinate . $this->line, $head);
            $this->headsKey[$key] = $coordinate;
            ++$num;
        }
        ++$this->line;

        return $this;
    }

    public function setCell(array $params)
    {
        if (!$this->headsKey) {
            $heads = collect($params)->map(function ($item, $key) {
                return $key;
            })->toArray();
            $this->setTableHead($heads);
        }
        foreach ($params as $key => $value) {
            if (!($coordinate = data_get($this->headsKey, $key))) {
                continue;
            }
            if ($format = data_get($this->cellFormat, $key)) {
                $this->sheet->setCellValueExplicit($coordinate . $this->line, $value, $format);
            } else {
                $this->sheet->setCellValue($coordinate . $this->line, $value);
            }
        }
        ++$this->line;

        return $this;
    }

    public function setCellFormat(array $params)
    {
        $this->cellFormat = $params;

        return $this;
    }

    public function buildFile(string $fileName = 'export')
    {
        $fileName = $fileName . '-' . time() . '.xlsx';
        $timePath = date('Ymd');
        $tmpDir = BASE_PATH . '/runtime/upload/excel/' . $timePath . '/'; // 类似于/www/public/upload/
        if (!file_exists($tmpDir)) {
            // 创建文件夹
            mkdir($tmpDir, 0777, true);
            go(function () use ($tmpDir, $timePath) {
                delete_dir($tmpDir, $timePath);
            });
        }
        $objWriter = $writer = new Xlsx($this->excel);  // Excel5
        $objWriter->save($tmpDir . $fileName);

        return $tmpDir . $fileName;
    }
}
