<?php

declare(strict_types=1);

namespace App\Library\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReaderExcel extends Excel
{
    /**
     * 文件路径
     * @var string
     */
    protected $filePath;

    /**
     * 是否需要读取第一行
     * @var bool
     */
    protected $needHead = false;

    /**
     * 读取最大列数(默认 0, 0=A)
     * @var int
     */
    protected $maxColumn = 0;

    /**
     * 自动获取最大列数
     * @var bool
     */
    protected $autoColumn = true;

    /**
     * 读取最大行数（默认 1 行,如果 needHead 为 false 读取行号 2 为第一行）
     * @var int
     */
    protected $maxRow = 1;

    /**
     * 自动获取最大行数
     * @var bool
     */
    protected $autoRow = true;

    /**
     * 工作簿(默认 0，第一个)
     * @var int
     */
    protected $sheet = 0;

    /**
     * 替换返回数据中的 column
     * @var array
     */
    protected $replaceColumn = [];

    public function __construct(string $filePath = '')
    {
        $this->setFilePath($filePath);
    }

    public function setFilePath(string $filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function setMaxColumn(int $num)
    {
        $this->autoColumn = false;
        $this->maxColumn = $num;

        return $this;
    }

    public function setMaxRow(int $num)
    {
        $this->autoRow = false;
        $this->maxRow = $num;

        return $this;
    }

    public function setReplaceColumn(array $replaces)
    {
        $this->replaceColumn = $replaces;

        return $this;
    }

    public function readExcel(): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($this->filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        if ($this->autoColumn){
            $this->maxColumn = Coordinate::stringFromColumnIndex($worksheet->getHighestColumn());
        }
        if ($this->autoRow){
            $this->maxRow = $worksheet->getHighestRow();
        }
        $row = $this->needHead ? 1 : 2;
        $data = [];
        for (;$row <= $this->maxRow; $row++) {
            $rowData = [];
            for($column = 1; $column <= $this->maxColumn; $column++){
                $cellValue = $worksheet->getCellByColumnAndRow($column, $row)->getValue()??'';
                $columnKey = Coordinate::stringFromColumnIndex($column);
                $columnKey = isset($this->replaceColumn[$columnKey])? $this->replaceColumn[$columnKey]: $columnKey;
                $rowData[$columnKey] = $cellValue;
            }
            $data[] = $rowData;
        }
        return $data;
    }
}