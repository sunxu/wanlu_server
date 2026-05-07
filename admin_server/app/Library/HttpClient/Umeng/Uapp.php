<?php

namespace App\Library\HttpClient\Umeng;

class Uapp extends BaseClient
{
    /**
     * 获取事件列表
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.list/1
     * @param array $params
     * @return mixed
     */
    public function eventList(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.list', $params);
    }

    /**
     * 获取事件参数列表
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.param.list/1
     * @param array $params
     * @return mixed
     */
    public function eventParamList(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.param.list', $params);
    }

    /**
     * 获取事件统计数据
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.getData/1
     * @param array $params
     * @return mixed
     */
    public function eventGetData(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.getData', $params);
    }

    /**
     * 获取自定义事件的独立用户数
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.getUniqueUsers/1
     * @param array $params
     * @return mixed
     */
    public function eventGetUniqueUser(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.getUniqueUsers', $params);
    }

    /**
     * 获取事件参数值列表
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.param.getValueList/1
     * @param array $params
     * @return mixed
     */
    public function eventParamGetValueList(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.param.getValueList', $params);
    }

    /**
     * 获取事件参数值统计数据
     * https://developer.umeng.com/open-api/docs/com.umeng.uapp/umeng.uapp.event.param.getData/1
     * @param array $params
     * @return mixed
     */
    public function eventParamGetData(array $params): array
    {
        return $this->post('/param2/1/com.umeng.uapp/umeng.uapp.event.param.getData', $params);
    }
}