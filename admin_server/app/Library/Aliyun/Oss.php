<?php

declare(strict_types=1);

namespace App\Library\Aliyun;

use App\Utils\Log;
use OSS\Core\OssException;
use OSS\OssClient;

class Oss
{
    protected $ossClient;

    public function __construct()
    {
        $ossConfig = config('oss');
        $this->ossClient = new OssClient(data_get($ossConfig, 'access_id'), data_get($ossConfig, 'access_secret'), data_get($ossConfig, 'endpoint'));
    }

    public function putPublicBucket(string $filePath)
    {
        return $this->putFile($filePath, true);
    }

    public function putFile(string $filePath, bool $isPublic)
    {
        if($isPublic){
            $bucket = config('oss.public_bucket');
        }else{
            $bucket = config('oss.private_bucket');
        }
        $fileName = substr($filePath, strrpos($filePath, '/') + 1);
        $ext = substr($fileName, strrpos($fileName, '.') + 1);
        $tmpPath = "uc/{$ext}/".date("Ymd")."/{$fileName}";
        try{
            $upload_result = $this->ossClient->multiuploadFile($bucket, $tmpPath, $filePath);
        }catch (OssException $e){
            Log::system('error', '上传文件oss失败', [$e->getMessage()]);
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }
        return [
            'oss_request_url' => data_get($upload_result, 'oss-request-url'),
            'relative_path' => $tmpPath,
        ];
    }

    public function putPrivateBucket(string $filePath)
    {
        return $this->putFile($filePath, false);
    }
}