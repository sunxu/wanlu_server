<?php
/**
 * 广告收益|穿山甲
 */

namespace App\Console\Commands\Report;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class ReportCsj extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "report-csj {date}";

    /**
     * Command description
     * @var string
     */
    protected $description = "投放收益|穿山甲";

    /**
     * 广告平台媒体
     * @var int
     */
    protected $mediaType = 1;

    protected $tableHeader = ['账号', '成功', '失败'];

    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        $date = $this->argument('date');
        $config = config('report.csj');
        $api = 'union_media/open_api/rt/income';
        foreach ($config['account'] as $accountKey => $account) {
            $this->tableBody[$accountKey] = [
                'account' => $accountKey,
                'success' => 0,
                'fail' => 0
            ];

            $params = [
                'user_id' => $account['user_id'],
                'role_id' => $account['role_id'],
                'timestamp' => time(),
                'version' => '2.0',
                "date" => date('Y-m-d', strtotime($date)),
                "region" => "cn",
                'sign_type' => 'MD5'
            ];
            $params['sign'] = $this->sign($params, $account['secret']);
            $result = Helper::request(sprintf('%s/%s', $config['request_url'], $api), 'GET', $params);
            if (empty($result['result'])) {
                continue;
            }
            $response = json_decode($result['result'], true);
            if (($response['Code'] ?? -1) !== '100' || empty($response['Data'][$date])) {
                continue;
            }
            foreach ($response['Data'][$date] as $v) {
                $input = [
                    'app_code' => $account['app'][$v['app_id']] ?? $v['app_id'],
                    'media_type' => $this->mediaType,
                    'date' => $v['date'],
                    'placement_id' => $v['ad_slot_id'],
                    'placement_type' => $config['placement_type'][$v['ad_slot_type']] ?? 0,
                    'placement_name' => $v['code_name'],
                    'scene' => stristr($v['code_name'], 'wg') ? 2 : 1,
                    'show' => $v['show'],
                    'click' => $v['click'],
                    'revenue' => $v['revenue'],
                    'ecpm' => $v['ecpm'],
                    'ad_request_num' => $v['request'],
                    'ad_return_num' => $v['return'],
                    'click_rate' => $v['click_rate'],
                    'ad_fill_rate' => $v['fill_rate'],
                    'avg_click' => $v['click'] == 0 ? 0 : $v['revenue'] / $v['click'],
                    'update_time' => time()
                ];
                $attributes = [
                    'placement_id' => $input['placement_id'],
                    'date' => $input['date']
                ];
                $r = DB::table('ad_report')->updateOrInsert($attributes, $input);
                $r ? $this->tableBody[$accountKey]['success']++ : $this->tableBody[$accountKey]['fail']++;
            }
        }
        $this->comment(str_replace('{date}', $date, $this->signature));
        $this->table($this->tableHeader, $this->tableBody);
        $this->callArtisan($date);
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
     * 命令调用
     * @param $date
     */
    public function callArtisan($date)
    {
        $this->call('report-ks', ['date' => $date]);
        $this->call('report-tx', ['date' => $date]);
    }
}