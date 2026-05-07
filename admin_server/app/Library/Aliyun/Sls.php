<?php

declare(strict_types=1);
/**
 * 阿里云日志相关操作
 * Author: jyk-wsy
 * Date: 2022/3/28
 */

namespace App\Library\Aliyun;

use Aliyun_Log_Client;
use Aliyun_Log_Exception;
use Aliyun_Log_Models_GetLogsRequest;
use Aliyun_Log_Models_LogItem;
use Aliyun_Log_Models_PutLogsRequest;
use Aliyun_Log_Models_QueriedLog;

class Sls
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
        $AccessKeyId = $this->AliyunConf['sls_access_key_id'];
        $accessKeySecret = $this->AliyunConf['sls_access_key_secret'];
        $endpoint = env('APP_ENV') == 'local' ? $this->AliyunConf['sls_point_outer'] : $this->AliyunConf['sls_point_inner'];//本地走外网，服务器走内网

        if (!isset($this->SlsClient) || !($this->SlsClient instanceof Aliyun_Log_Client)) {
            $this->SlsClient = new Aliyun_Log_Client($endpoint, $AccessKeyId, $accessKeySecret);
        }
        return $this->SlsClient;
    }

    /**
     * 设置 logstore
     * @param $logstore
     * @return Sls
     */
    public function setLogstore($logstore): Sls
    {
        $this->logstore = $logstore;
        return $this;
    }

    /**
     * 设置 topic
     * @param $topic
     * @return $this
     */
    public function setTopic($topic): Sls
    {
        $this->topic = $topic;
        return $this;
    }

    /**
     * 日志上报 每条日志务必指定created_time字段
     * @throws Aliyun_Log_Exception
     */
    public function putLogs($contents): \Aliyun_Log_Models_PutLogsResponse
    {
        $logItems = [];
        foreach ($contents as $content) {
            $logItem = new Aliyun_Log_Models_LogItem();
            $logItem->setTime($content['created_time'] ?? time());
            $logItem->setContents($content);
            $logItems[] = $logItem;
        }

        $request = new Aliyun_Log_Models_PutLogsRequest(
            $this->project,
            $this->logstore,
            $this->topic,
            "",
            $logItems
        );
        return $this->getClient()->putLogs($request);
    }

    /**
     * 通过sql查询数据
     * @param int $from 开始时间戳 该时间是指写入日志数据时指定的日志时间
     * @param int $to 结束时间戳 该时间是指写入日志数据时指定的日志时间
     * @param string $query 查询语句
     * @param int|null $line 查询最大条数。最小值为0，最大值为100，默认值为100
     * @param int $offset 查询开始行。默认值为0
     * @param bool $reverse 排序 true倒序 false正序
     * @return array
     * @throws Aliyun_Log_Exception
     */
    public function getLogsWithSql(int $from, int $to, string $query, int $line = null, int $offset = 0, bool $reverse = true): array
    {
        $request = new Aliyun_Log_Models_GetLogsRequest($this->project, $this->logstore, $from, $to, $this->topic, $query, $line, $offset, $reverse);
        $response = $this->getClient()->getLogs($request);

//        if (env('APP_ENV') == 'local') {
//            foreach ($response->getLogs() as $log) {
//                print "日志时间:" . date('Y-m-d H:i:s', (int)$log->getTime()) . PHP_EOL;
//                print "日志数据:" . json_encode($log->getContents()) . PHP_EOL;
//                print PHP_EOL;
//            }
//            print "结果行数:" . $response->getCount() . PHP_EOL;
//            print "查询毫秒:" . $response->getElapsedMilli() . PHP_EOL;
//            print "cpuSec:" . $response->getCpuSec() . PHP_EOL;
//            print "cpuCores:" . $response->getCpuCores() . PHP_EOL;
//            print "requestId:" . $response->getRequestId() . PHP_EOL;
//        }

        $res_data = [];
        $res_data['rows'] = $response->getProcessedRows();
        $res_data['count'] = $response->getCount();
        $res_data['elapsed_milli'] = $response->getElapsedMilli();
        $res_data['cpu_sec'] = $response->getCpuSec();
        $res_data['cpu_cores'] = $response->getCpuCores();
        $res_data['request_id'] = $response->getRequestId();
        $res_data['log_list'] = [];
        /** @var Aliyun_Log_Models_QueriedLog $log */
        foreach ($response->getLogs() as $log) {
            $list = [];
            $list['time'] = (int)$log->getTime();
            $list['contents'] = $log->getContents();
            $list['source'] = $log->getSource();
            $res_data['log_list'][] = $list;
        }

        return $res_data;

    }
}
