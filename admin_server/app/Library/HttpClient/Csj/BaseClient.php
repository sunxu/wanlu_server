<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Csj;

use App\Library\HttpClient\ClientAbstract;
use App\Model\AppCompany;
use Hyperf\Utils\Str;

abstract class BaseClient extends ClientAbstract
{
    protected $conf = [];

    public function getClientName(): string
    {
        return 'csj';
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
        $options = array_merge([
            'user_id' => intval(data_get($this->conf, 'user_id')),
            'role_id' => intval(data_get($this->conf, 'role_id')),
            'timestamp' => time() * 1000,
            'sign_type' => 'MD5',
            'version' => '2.0',
        ], $options);
        $options['sign'] = $this->signature($options);

        return parent::buildOptions(['json' => $options], $headers, $method, $uri);
    }

    protected function signature($params) {
        ksort($params);
        $str = http_build_query($params);
        $str = urldecode($str);
        $str .= data_get($this->conf, 'secret');

        return md5($str);
    }
}