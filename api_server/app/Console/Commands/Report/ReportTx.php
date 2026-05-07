<?php
/**
 * 广告收益|腾讯优量汇
 */

namespace App\Console\Commands\Report;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class ReportTx extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "report-tx {date}";

    /**
     * Command description
     * @var string
     */
    protected $description = "投放收益|腾讯优量汇";

    /**
     * 广告平台媒体
     * @var int
     */
    protected $mediaType = 2;

    protected $tableHeader = ['账号', '成功', '失败'];

    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        $date = $this->argument('date');
        $config = config('report.tx');
        $api = 'open/v1.1/report/get';
        foreach ($config['account'] as $accountKey => $account) {
            $this->tableBody[$accountKey] = [
                'account' => $accountKey,
                'success' => 0,
                'fail' => 0
            ];
            $headers = $headers = [
                'Content-Type' => 'application/json',
                'token' => $this->getToken($account['user_id'], $account['secret'])
            ];
            $params = [
                "member_id" => $account['user_id'],
                "start_date" => date('Ymd', strtotime($date)),
                "end_date" => date('Ymd', strtotime($date)),
            ];
            $result = Helper::request(sprintf('%s/%s', $config['request_url'], $api), 'GET', $params, $headers);
            if (empty($result['result'])) {
                continue;
            }
            $response = json_decode($result['result'], true);
            if (($response['code'] ?? -1) !== 0 || empty($response['data']['list'])) {
                continue;
            }
            foreach ($response['data']['list'] as $v) {
                if (empty($v['placement_id'])) {
                    continue;
                }
                $input = [
                    'app_code' => $account['app'][$v['app_id']] ?? $v['app_id'],
                    'media_type' => $this->mediaType,
                    'date' => $v['date'],
                    'placement_id' => $v['placement_id'],
                    'placement_type' => $config['placement_type'][$v['custom_position_scene_level2']] ?? 0,
                    'placement_name' => $v['placement_name'],
                    'scene' => stristr($v['placement_name'], 'wg') ? 2 : 1,
                    'show' => $v['pv'],
                    'click' => $v['click'],
                    'revenue' => $v['revenue'],
                    'ecpm' => $v['ecpm'],
                    'ad_request_num' => $v['request_count'],
                    'ad_return_num' => $v['return_count'],
                    'click_rate' => $v['click_rate'],
                    'ad_fill_rate' => $v['fill_rate'],
                    'ad_exposure_rate' => $v['exposure_rate'],
                    'cpc' => $v['cpc'],
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
    }

    /**
     * 获取Token
     * @param $userId
     * @param $secret
     * @return string
     */
    protected function getToken($userId, $secret)
    {
        $time = time();
        return base64_encode($userId . ',' . $time . ',' . sha1($userId . $secret . $time));
    }
}