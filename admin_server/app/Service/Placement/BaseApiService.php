<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Service\Placement;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\ModelTools\PlacementAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

abstract class BaseApiService
{
    const CURL_TIMEOUT = 30;//接口请求超时时间（秒）

    protected $httpClient;
    protected $Account;
    protected $Conf;

    public function __construct($account_id)
    {
        $this->Account = $account_id instanceof PlacementAccount ? $account_id : PlacementAccount::find($account_id);
        if (!$this->Account) {
            throw new BusinessException(ErrorCode::PARAMS_ERROR, '主体账号信息错误！');
        }
        $this->Conf = json_decode($this->Account->conf, true);
        $this->httpClient = new Client(['timeout' => self::CURL_TIMEOUT]);
    }

    /**
     * 查询APP
     * @return mixed
     */
    abstract public function queryApp();

    /**
     * 创建代码位
     * @param array $params
     * @return mixed
     */
    abstract public function create(array $params);

    /**
     * 发送请求
     * @param $method
     * @param $url
     * @param $options
     * @return \Psr\Http\Message\ResponseInterface
     * @throws GuzzleException
     */
    public function sendRequest($method, $url, $options): \Psr\Http\Message\ResponseInterface
    {
        return $this->httpClient->request($method, $url, $options);
    }
}