<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/3/30
 */

namespace App\Services\Common;

use Aliyun_Log_Client;
use Aliyun_Log_Exception;
use Aliyun_Log_Models_GetLogsRequest;
use Aliyun_Log_Models_LogItem;
use Aliyun_Log_Models_PutLogsRequest;

class AliyunSls
{

    public $AliyunConf;
    public $SlsClient;
    public $project;
    public $logstore;
    public $topic = '';

    public function __construct()
    {
        $this->AliyunConf = config('aliyun');
        $this->project = $this->AliyunConf['sls_project'];
    }

    /**
     * 获取Sls client
     * @return Aliyun_Log_Client
     */
    public function getClient(): Aliyun_Log_Client
    {
        $AccessKeyId = $this->AliyunConf['access_key_id'];
        $accessKeySecret = $this->AliyunConf['access_key_secret'];
        $endpoint = env('APP_ENV') == 'local' ? $this->AliyunConf['sls_point_outer'] : $this->AliyunConf['sls_point_inner'];//本地走外网，服务器走内网

        if (!isset($this->SlsClient) || !($this->SlsClient instanceof Aliyun_Log_Client)) {
            $this->SlsClient = new Aliyun_Log_Client($endpoint, $AccessKeyId, $accessKeySecret);
        }
        return $this->SlsClient;
    }

    /**
     * 设置 logstore
     * @param $logstore
     * @return $this
     */
    public function SetLogstore($logstore): AliyunSls
    {
        $this->logstore = $logstore;
        return $this;
    }

    /**
     * 设置 topic
     * @param $topic
     * @return $this
     */
    public function SetTopic($topic): AliyunSls
    {
        $this->topic = $topic;
        return $this;
    }

    /**
     * 日志上报
     * @throws Aliyun_Log_Exception
     */
    public function PutLogs($contents): \Aliyun_Log_Models_PutLogsResponse
    {
        $logItems = [];
        foreach ($contents as $content) {
            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime($content['log_time'] ?? time());
            $logItem->setContents($content);
            $logItems[] = $logItem;
        }
        $request = new Aliyun_Log_Models_PutLogsRequest(
            $this->project,
            $this->logstore,
            $this->topic,
            '',
            $logItems
        );
        return $this->getClient()->putLogs($request);
    }

    /**
     * 通过sql查询数据
     * @param int $from
     * @param int $to
     * @param string $query
     * @return \Aliyun_Log_Models_GetLogsResponse
     * @throws Aliyun_Log_Exception
     */
    public function getLogsWithSql(int $from, int $to, string $query): \Aliyun_Log_Models_GetLogsResponse
    {
        $request = new Aliyun_Log_Models_GetLogsRequest($this->project, $this->logstore, $from, $to, $this->topic, $query);
        return $this->getClient()->getLogs($request);

    }
}