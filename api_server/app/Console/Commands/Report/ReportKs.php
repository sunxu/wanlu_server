<?php
/**
 * 广告收益|快手
 */

namespace App\Console\Commands\Report;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class ReportKs extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "report-ks {date}";

    /**
     * Command description
     * @var string
     */
    protected $description = "投放收益|快手";

    /**
     * 广告平台媒体
     * @var int
     */
    protected $mediaType = 3;

    protected $tableHeader = ['账号', '成功', '失败'];

    protected $tableBody = [];

    /**
     * Command Handle
     */
    public function handle()
    {
        $date = $this->argument('date'); //格式为y-M-d
        $config = config('report.ks');
        $api = 'api/report/dailyShare';
        foreach ($config['account'] as $accountKey => $account) {
            $this->tableBody[$accountKey] = [
                'account' => $accountKey,
                'success' => 0,
                'fail' => 0
            ];
            $params = [
                'ak' => $account['user_id'],
                'sk' => $account['secret'],
                'timestamp' => time(),
                'page' => 1,
                "date" => date('Y-m-d', strtotime($date))
            ];
            $params['sign'] = $this->sign($params, $api);
            $result = Helper::request(sprintf('%s/%s', $config['request_url'], $api), 'GET', $params);
            if (empty($result['result'])) {
                continue;
            }
            $response = json_decode($result['result'], true);
            if (($response['result'] ?? -1) !== 1 || empty($response['data'])) {
                continue;
            }
            foreach ($response['data'] as $v) {
                $input = [
                    'app_code' => $account['app'][$v['app_id']] ?? $v['app_id'],
                    'media_type' => $this->mediaType,
                    'date' => $v['date'],
                    'placement_id' => $v['position_id'],
                    'placement_type' => $config['placement_type'][$v['ad_style']] ?? 0,
                    'placement_name' => $v['position_name'],
                    'scene' => stristr($v['position_name'], 'wg') ? 2 : 1,
                    'show' => $v['impression'],
                    'click' => $v['click'],
                    'revenue' => $v['share'],
                    'ecpm' => $v['ecpm'],
                    'avg_click' => $v['click'] == 0 ? 0 : $v['share'] / $v['click'],
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
     * 签名
     * @param array $param
     * @param string $api
     * @return string
     */
    public function sign(array $param, string $api): string
    {
        // 过滤空值
        $param = array_filter($param, 'strlen');
        // 排序
        ksort($param);
        // 生成签名
        return md5(sprintf('/%s?%s', $api, urldecode(http_build_query($param))));
    }
}