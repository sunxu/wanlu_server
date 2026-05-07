<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Csjmedia;

use App\Library\HttpClient\ClientAbstract;
use App\Model\AppCompany;
use App\ModelTools\PlacementAccount;

abstract class BaseClient extends ClientAbstract
{
    const API_VERSION = '1.0';//接口版本号

    protected $conf = [];

    public function getClientName(): string
    {
        return 'csjmedia';
    }

    public function setAccount(int $accountId)
    {
        $conf = AppCompany::select('media_conf_csj')->where('company_id', $accountId)->first();
        $this->conf = data_get($conf, 'media_conf_csj');
        return $this;
    }

    public function setConf($conf)
    {
        $this->conf = $conf;
        return $this;
    }

    protected function buildOptions(array $options, array $headers, string $method, string $uri)
    {
        $timestamp = time();
        $nonce = $this->getNonce();//长度与timestamp保持一致，否则可能会导致签名失败
        $user_id = data_get($this->conf, 'user_id');
        $role_id = data_get($this->conf, 'role_id');
        $secret = data_get($this->conf, 'secret');
        $sign = $this->getSign($timestamp, $nonce, $secret);
        $version = self::API_VERSION;
        $options = array_merge($options, [
            'user_id' => $user_id,
            'role_id' => $role_id,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
            'version' => $version,
        ]);
        return parent::buildOptions(['json' => $options], $headers, $method, $uri);
    }

    /**
     * 获取随机字符串
     * @return int
     */
    protected function getNonce(): int
    {
        return rand(1000000000, 9999999999);
    }

    protected function getSign($time, $nonce, $secret): string
    {
        $nonce = (string)$nonce;
        $time = (string)$time;
        $arr = [$nonce, $time, $secret];
        sort($arr);
        //返回新字符串
        $str = implode('', $arr);
        return sha1($str);
    }
}