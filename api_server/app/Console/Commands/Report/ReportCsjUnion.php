<?php
/**
 * 穿山甲聚合管理｜代码位
 */

namespace App\Console\Commands\Report;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class ReportCsjUnion extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "report-csj-union";

    /**
     * Command description
     * @var string
     */
    protected $description = "代码位信息|穿山甲";

    /**
     * @var array
     */
    protected $config = [];

    protected $tableHeader = ['账号', '成功', '失败'];

    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        $this->config = config('report.csj');
        foreach ($this->config['account'] as $accountKey => $account) {
            $this->tableBody[$accountKey] = [
                'account' => $accountKey,
                'success' => 0,
                'fail' => 0
            ];
            foreach ($this->appList($accountKey) as $app) {
                foreach ($this->adList($accountKey, $app['app_id']) as $ad) {
                    foreach ($this->adDetail($accountKey, $ad['ad_unit_id']) as $d) {
                        $input = [
                            'app_code' => $account['app'][$app['app_id']] ?? $app['app_id'],
                            'app_id' => $app['app_id'],
                            'ad_union_id' => $ad['ad_unit_id'],
                            'ad_slot_id' => $d['ad_slot_id'],
                            'price' => $d['price'] ?? 0,
                            'update_time' => time()
                        ];
                        $attributes = [
                            'ad_union_id' => $ad['ad_unit_id'],
                            'ad_slot_id' => $input['ad_slot_id']
                        ];
                        $r = DB::table('ad_csj')->updateOrInsert($attributes, $input);
                        $r ? $this->tableBody[$accountKey]['success']++ : $this->tableBody[$accountKey]['fail']++;
                    }
                }
            }
        }
        $this->comment($this->signature);
        $this->table($this->tableHeader, $this->tableBody);
    }


    /**
     * 签名
     * @param array $param
     * @param string $secret
     * @return string
     */
    public function sign(array $param, string $secret): string
    {
        // 过滤空值
        $param = array_filter($param, 'strlen');
        // 排序
        ksort($param);
        // 生成签名
        return md5(urldecode(http_build_query($param)) . $secret);
    }

    /**
     * 统一请求
     * @param $api
     * @param $accountKey
     * @param array $params
     * @return array|mixed
     */
    public function request($api, $accountKey, $params = [])
    {
        $params = array_merge([
            'user_id' => $this->config['account'][$accountKey]['user_id'],
            'role_id' => $this->config['account'][$accountKey]['role_id'],
            'timestamp' => time() * 1000,
            'version' => '2.0',
            'sign_type' => 'MD5',
        ], $params);
        $params['sign'] = $this->sign($params, $this->config['account'][$accountKey]['secret']);
        $result = Helper::request(sprintf('%s/%s', $this->config['request_url'], $api), 'GET', $params);
        return empty($result['result']) ? [] : json_decode($result['result'], true);
    }

    /**
     * 聚合应用列表
     * @param $accountKey
     * @return array
     */
    public function appList($accountKey)
    {
        $result = $this->request('union_media/open/api/mediation/app', $accountKey);
        $list = [];
        if (($result['code'] ?? -1) === '100' && !empty($result['data']['app_list'])) {
            $list = $result['data']['app_list'];
        }
        return $list;
    }

    /**
     * 广告位列表
     * @param $appId
     * @param $accountKey
     * @return array
     */
    public function adList($accountKey, $appId)
    {
        $result = $this->request('union_media/open/api/mediation/adunits', $accountKey, [
            'app_id' => $appId
        ]);
        $list = [];
        if (($result['code'] ?? -1) === '100' && !empty($result['data']['ad_unit_list'])) {
            $list = $result['data']['ad_unit_list'];
        }
        return $list;
    }

    /**
     * 广告位列表
     * @param $adId
     * @param $accountKey
     * @return array
     */
    public function adDetail($accountKey, $adId)
    {
        $result = $this->request('union_media/open/api/mediation/waterfall_detail', $accountKey, [
            'ad_unit_id' => $adId
        ]);
        $list = [];
        if (($result['code'] ?? -1) === '100' && !empty($result['data']['ad_slot_list'])) {
            $list = $result['data']['ad_slot_list'];
        }
        return $list;
    }
}