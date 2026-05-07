<?php

namespace App\Service\Lianyun;

use App\Model\App;
use App\Model\Mkt\ReportDailyLianyun;
use App\Utils\Ding;
use App\Utils\Http;
use Exception;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Exception\GuzzleException;

class DataService
{
    protected $baseUri = 'http://api.center.kumengkeji.cn';

    protected $loginName = 'zhilianwifi';
    protected $loginPassword = 'JYK888!';
    protected $loginUri = '/agent/my/login';
    protected $dataUri;
    protected $defaultDataUri = '/agent/data/putdata?app_id=%s&date=%s+-+%s';
    protected $token;
    protected $appList = [];

    /**
     * @param string $date
     * @throws Exception
     */
    public function handle(string $date)
    {
        $this->updateApp();

        $this->updateData($date);

    }

    /**
     * @param string $date
     * @throws Exception
     */
    protected function updateData(string $date)
    {
        $appList = App::query()
            ->where('ly_appid', '!=', '')
            ->pluck('ly_appid', 'app_code')
            ->toArray();

        foreach ($appList as $appCode => $appId) {
            $data = $this->getPutData($appId, $date);
            if (!empty($data)) {
                ReportDailyLianyun::updateOrCreate([
                    'date' => $date,
                    'app_code' => $appCode,
                ], [
                    'cost' => floatval($data['消耗'] ?? 0),
                    'active' => floatval($data['激活数'] ?? 0),
                    'active_cost' => floatval($data['激活成本'] ?? 0),
                    'active_rate' => floatval($data['激活率'] ?? 0),
                    'next_day_open' => floatval($data['次留数'] ?? 0),
                    'next_day_open_rate' => floatval($data['次留率'] ?? 0),
                    'convert' => floatval($data['转化数'] ?? 0),
                    'convert_cost' => floatval($data['转化成本'] ?? 0),
                    'convert_rate' => floatval($data['转化率'] ?? 0),
                ]);
            }
        }
    }

    protected function getPutData(string $appId, string $date)
    {
        $uri = sprintf($this->defaultDataUri, $appId, $date, $date);
        try {
            $response = $this->getClient()->request("GET", $uri, [
                'headers' => [
                    'Cookie' => $this->getToken()
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $html = $response->getBody()->getContents();

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $theader = $xpath->query('//table[@class="table"]/thead/tr[1]/th');
        $tbody = $xpath->query('//table[@class="table"]/tbody/tr[1]/*');
        $name = [];
        $value = [];
        foreach ($theader as $key => $element) {
            $name[] = $element->nodeValue;
        }
        foreach ($tbody as $key => $element) {
            $value[] = $element->nodeValue;
        }

        if (count($name) == count($value)) {
            return array_combine($name, $value);
        }

        return [];
    }

    protected function savePutData(string $appCode, array $data)
    {

    }

    /**
     * @throws Exception
     */
    protected function updateApp()
    {
        $appList = $this->getAppList();
        if (empty($appList)) {
            make(Ding::class)->setRobotCode('warning')
                ->pushText('联运获取应用列表失败');
        }
        foreach ($appList as $appId => $appName) {
            $appCode = $this->appCode($appName);
            if ($appCode) {
                App::query()
                    ->where('app_code', $appCode)
                    ->where('ly_appid', '')
                    ->update([
                        'ly_appid' => $appId
                    ]);
            }
        }
    }

    /**
     * 应用代码
     * @param $name
     * @return int|string
     */
    protected function appCode($name)
    {
        if (empty($this->appList)) {
            $this->appList = (new App())->appList([], [], [], [], []);
        }

        foreach ($this->appList as $key => $value) {
//            if (strstr($name, $value) !== false) {
//                $appCode = $key;
//                break;
//            }
            if ($name == $value) {
                $appCode = $key;
                break;
            }
        }

        return $appCode ?? '';
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getAppList()
    {
        try {
            $response = $this->getClient()->request("GET", $this->getDataUri(), [
                'headers' => [
                    'Cookie' => $this->getToken()
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $html = $response->getBody()->getContents();
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $elements = $xpath->query('//ul[@class="hide-menu"]/li');
        $appList = [];
        foreach ($elements as $element) {
            $text = $xpath->evaluate("string(./a[1])", $element);
            $href = $xpath->evaluate("string(./a[1]/@href)", $element);
            parse_str($href, $arr);
            foreach ($arr as $k => $v) {
                $appList[$v] = $text;
            }
        }
        return $appList;
    }

    /**
     * 登陆后的首页
     * @throws Exception
     */
    protected function getDataUri()
    {
        if ($this->dataUri) {
            return $this->dataUri;
        }
        try {
            $response = $this->getClient()->request("GET", $this->loginUri, [
                'headers' => [
                    'Cookie' => $this->getToken(),
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $uri = $response->getHeader('X-Guzzle-Redirect-History')[0] ?? '';

        $uri = str_replace($this->baseUri, '', $uri);
        $this->dataUri = $uri ? $uri : $this->defaultDataUri;
        return $this->dataUri;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getToken()
    {
        if (!empty($this->token)) {
            return $this->token;
        }
        try {
            $response = $this->getClient()->request("POST", $this->loginUri, [
                'form_params' => [
                    "username" => $this->loginName,
                    "password" => $this->loginPassword,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $contents = json_decode($response->getBody()->getContents(), true);
        if (($contents['code'] ?? -1) !== 0) {
            throw new Exception(json_encode($contents, 320));
        }
        $cookie = $response->getHeader('set-cookie')[0] ?? '';
        $this->token = explode(';', $cookie)[0] ?? '';
        if (empty($this->token)) {
            throw new Exception('模拟登陆失败');
        }
        return $this->token;
    }

    protected function getClient()
    {
        return make(Http::class)->get([
            'allow_redirects' => ['track_redirects' => true],
            'base_uri' => $this->baseUri
        ]);
    }
}