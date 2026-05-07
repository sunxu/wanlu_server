<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\Ad\Placement;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;

class UploadAction extends AbstractAction
{

    public function handle()
    {
        $File = $this->request->file('file');
        if ($File->getExtension() != 'csv') {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '请上传csv文件');
        }
        $path = $File->getRealPath();

        $data = [];
        $data['list'] = $this->getCsvData($path);
        return $this->responseSuccess($data);
    }


    public function getCsvData($file)
    {
        if (!is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            return [];
        }

        $i = 0;
        $list = [];
        while (($data = fgetcsv($handle)) !== false) {
            $i++;
            // 跳过第一行标题
            if ($i == 1) {
                continue;
            }
            // data 为每行的数据，这里转换为一维数组
            $list[] = $data;
        }

        fclose($handle);

        return $list;
    }
}