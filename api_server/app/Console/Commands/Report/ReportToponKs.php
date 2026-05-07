<?php
/**
 * 广告收益|Topon
 */

namespace App\Console\Commands\Report;

use App\Services\Helper\Helper;
use DB;
use Illuminate\Console\Command;

class ReportToponKs extends Command
{
    /**
     * @var string Command Name
     */
    protected $signature = "report-topon-ks {date}";

    /**
     * Command description
     * @var string
     */
    protected $description = "投放收益|快手";

    /**
     * 配置
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
        $date = $this->argument('date');
        $this->config = config('report.toponKs');
        foreach ($this->config['account'] as $accountKey => $account) {
            $this->tableBody[$accountKey] = [
                'account' => $accountKey,
                'success' => 0,
                'fail' => 0
            ];
            $this->save($date, 1, $accountKey, $account);
        }
        $this->comment(str_replace('{date}', $date, $this->signature));
        $this->table($this->tableHeader, $this->tableBody);
    }

    /**
     * 拉取入库
     * @param $date
     * @param $page
     * @param $accountKey
     * @param $account
     */
    public function save($date, $page, $accountKey, $account)
    {
        $pageSize = 1000;
        $params = [
            "startdate" => (int)date('Ymd', strtotime($date)),
            "enddate" => (int)date('Ymd', strtotime($date)),
            'group_by' => ['date', 'app', 'placement', 'adsource','adformat'],
            'metric' => ['all'],
            'start' => ($page - 1) * $pageSize,
            'limit' => $pageSize,
        ];
        $result = $this->doRequest(sprintf('%s/%s', $this->config['request_url'], 'v2/fullreport'), $params, $account['secret']);

        if ($result['code'] == 0 && !empty($result['data']['records'])) {
            foreach ($result['data']['records'] as $v) {
                $v['adsource']['token'] = empty($v['adsource']['token']) ? [] : json_decode($v['adsource']['token'], true);
                $data = [
                    'date' => date('Y-m-d', strtotime($v['date'])),
                    'ad_request_num' => $v['request'] ?? 0,
                    'ad_return_num' => round($v['request'] * $v['fillrate']) ?? 0,
                    'placement_id' => $v['adsource']['token']['slot_id'] ?? ($v['adsource']['token']['position_id'] ?? ($v['adsource']['token']['unit_id'] ?? '')),
                ];
                $r = DB::table('ad_report')
                    ->where('date',$data['date'])
                    ->where('placement_id',$data['placement_id'])
                    ->where('media_type',3)
                    ->update(['ad_request_num'=>$data['ad_request_num'],'ad_return_num'=>$data['ad_return_num']]);
                $r ? $this->tableBody[$accountKey]['success']++ : $this->tableBody[$accountKey]['fail']++;
            }
            if (count($result['data']['records']) <= $pageSize) {
                $this->save($date, $page + 1, $accountKey, $account);
            }
        }
    }

    /**
     * 统一请求
     * @param string $reqUrl
     * @param array $contentArr
     * @param $key
     * @param string $httpMethod
     * @return array
     */
    public function doRequest(string $reqUrl, array $contentArr, $key, $httpMethod = 'POST')
    {
        $headerArr = [
            'X-Up-Key' => $key,
            'X-Up-Timestamp' => time() * 1000,
        ];

        // create the content of common headers except the header 'X-Up-Signature'.
        $headerStr = '';
        foreach ($headerArr as $key => $value) {
            if (empty($headerStr)) {
                $headerStr = "$key:$value";
            } else {
                $headerStr = "$headerStr\n$key:$value";
            }
        }

        // create the MD5 value of the request body.
        // convert to the query param for the 'GET' method.
        if ($httpMethod == "GET") {
            $reqUrl = $reqUrl . "?" . http_build_query($contentArr);
            $contentStr = "";
        } else {
            $contentStr = json_encode($contentArr);
        }
        $contentMD5 = strtoupper(md5($contentStr));
        // create the final signature.
        $contentType = 'application/json';
        $relativePath = parse_url($reqUrl)["path"];
        $signStr = $httpMethod . "\n"
            . $contentMD5 . "\n"
            . $contentType . "\n"
            . $headerStr . "\n"
            . $relativePath;
        $headerArr['X-Up-Signature'] = strtoupper(md5($signStr));;
        $headerArr['Content-Type'] = $contentType;

        $result = Helper::request($reqUrl, $httpMethod, $contentStr, $headerArr);
        return Helper::return($result['http_code'] == 200 ? 0 : 100, json_decode($result['result'], true));
    }
}