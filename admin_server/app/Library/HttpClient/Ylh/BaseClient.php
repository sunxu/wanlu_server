<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Ylh;

use App\Library\HttpClient\ClientAbstract;
use App\Model\AppCompany;
use App\ModelTools\PlacementAccount;

abstract class BaseClient extends ClientAbstract
{
    protected $conf = [];

    public function getClientName(): string
    {
        return 'ylh';
    }

    public function setAccount(int $accountId)
    {
        $conf = AppCompany::select('media_conf_ylh')->where('company_id', $accountId)->first();
        $this->conf = data_get($conf, 'media_conf_ylh');
        return $this;
    }

    public function setConf($conf)
    {
        $this->conf = $conf;
        return $this;
    }

    protected function buildOptions(array $options, array $headers, string $method, string $uri)
    {
        data_set($headers, 'token', $this->getToken());
        $options = array_merge([
            'member_id' => intval(data_get($this->conf, 'user_id'))
        ], $options);
        return parent::buildOptions(['json' => $options], $headers, $method, $uri);
    }

    protected function getToken(): string
    {
        $member_id = data_get($this->conf, 'user_id');
        $secret = data_get($this->conf, 'secret');
        $timestamp = time();
        $sign = sha1($member_id . $secret . $timestamp);
        return base64_encode($member_id . ',' . $timestamp . ',' . $sign);
    }
}