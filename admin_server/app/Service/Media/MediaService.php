<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Constants\MediaConst;
use App\Library\Media\MediaBD;
use App\Library\Media\MediaCSJ;
use App\Library\Media\MediaHW;
use App\Library\Media\MediaKS;
use App\Library\Media\MediaOP;
use App\Library\Media\MediaVI;
use App\Library\Media\MediaYLH;
use App\Model\App;
use App\Model\AppMediaApp;
use App\Model\Media\MediaAd;
use App\Model\Media\MediaIncome;
use Exception;

class MediaService
{
    /**
     * 服务类映射
     * @var array
     */
    protected $serviceClass = [
        'csj' => MediaCSJ::class,
        'ylh' => MediaYLH::class,
        'ks' => MediaKS::class,
        'hw' => MediaHW::class,
        'vi' => MediaVI::class,
        'op' => MediaOP::class,
        'bd' => MediaBD::class,
    ];

    /**
     * 媒体服务
     * @var MediaKS
     */
    protected $service;

    /**
     * 媒体对应应用
     * @var array
     */
    protected $mediaApp;

    /**
     * 应用列表
     * @var array
     */
    protected $appList;

    protected $companyId;

    protected $mediaAppList;

    /**
     * MediaService constructor.
     * @param string $media
     * @param array $mediaConf
     * @param array $extra
     * @throws Exception
     */
    public function __construct(string $media, array $mediaConf, array $extra = [])
    {
        $media = strtolower($media);

        if (!isset($this->serviceClass[$media])) {
            throw new Exception('error media type');
        }
        if (empty($mediaConf['user_id']) || empty($mediaConf['secret'])) {
            throw new Exception('error media conf');
        }

        $this->service = make($this->serviceClass[$media], [$mediaConf]);

        $this->mediaApp = $extra['media_app'] ?? [];

        $this->companyId = $extra['company_id'] ?? 0;
    }

    /**
     * 媒体日回收
     * @param string $date
     * @throws Exception
     */
    public function income(string $date)
    {
        $page = 1;
        $totalPage = 1;
        $pageSize = 1000;

        while ($page <= $totalPage) {
            $data = $this->service->income($date, $page, $pageSize);
            $totalPage = $data['page_info']['total_page'] ?? 1;
            $page++;
            foreach ($data['list'] ?? [] as $d) {
                $this->saveIncome($d);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function adList()
    {
        $page = 1;
        $totalPage = 1;
        $pageSize = 100;

        while ($page <= $totalPage) {
            $data = $this->service->adList($page, $pageSize);
            $totalPage = $data['page_info']['total_page'] ?? 1;
            $page++;
            foreach ($data['list'] ?? [] as $d) {
                $this->saveAd($d);
            }
        }
    }

    /**
     * 回收入库
     * @param array $data
     * @return bool
     */
    protected function saveIncome(array $data)
    {
        if (($data['revenue'] ?? 0) <= 0) {
            return false;
        }

        $data['media_company'] = $this->companyId;
        $data['app_code'] = $this->matchAppCode($data['app_id'], $data['app_name']);
        $data['placement_type'] = $this->placemenType($data['placement_name']) ?? $data['placement_type'];

        $log_file = '/tmp/saveIncome.txt';
        $time = date("Y-m-d H:i:s");
        $message = json_encode([
            'date' => $data['date'],
            'placement_id' => $data['placement_id']
        ], JSON_UNESCAPED_UNICODE);
        $log_message = "$time $message \n";
        file_put_contents($log_file, $log_message, FILE_APPEND);

        return MediaIncome::updateOrCreate([
            'date' => $data['date'],
            'placement_id' => $data['placement_id']
        ], $data)->save();
    }

    protected function saveAd(array $data)
    {
        if (empty($data['app_name'])) {
            // ks ylh
            $data['app_name'] = $this->mediaAppName($data['app_id']);
        }
        $data['media_company'] = $this->companyId;
        $data['app_code'] = $this->matchAppCode($data['app_id'], $data['app_name']);
        $data['placement_type'] = $this->placemenType($data['placement_name']) ?? $data['placement_type'];
        $data['bidding'] = $data['bidding'] ?? (strstr($data['placement_name'], 'bidding') !== false ? 1 : 0);

        return MediaAd::updateOrCreate([
            'placement_id' => $data['placement_id']
        ], $data)->save();
    }

    /**
     * 代码类型
     * @param $name
     * @return string|null
     */
    protected function placemenType($name)
    {
        foreach (MediaConst::PLACEMENT_NAME_TYPE as $key => $value) {
            if (strstr($name, $key) !== false) {
                $placementType = $value;
                break;
            }
        }

        return $placementType ?? null;
    }

    /**
     * 应用代码
     * @param $name
     * @return int|string
     */
    protected function appCode($name)
    {
        if (!isset($this->appList)) {
            $this->appList = (new App())->appList([], [], [], [], []);
        }

        foreach ($this->appList as $key => $value) {
            if (strstr($name, $value) !== false) {
                $appCode = $key;
                break;
            }
        }

        return $appCode ?? '';
    }

    /**
     * @return array
     */
    protected function getMediaAppList()
    {
        if (!isset($this->mediaAppList)) {
            $list = AppMediaApp::query()
                ->select([
                    'app_id',
                    'app_name',
                    'app_code'
                ])
                ->get()
                ->toArray();
            $data = [];
            foreach ($list as $v) {
                $data[$v['app_id']] = $v;
            }

            $this->mediaAppList = $data;
        }
        return $this->mediaAppList;
    }

    protected function mediaAppName($mediaAppId)
    {
        return $this->getMediaAppList()[$mediaAppId]['app_name'] ?? '';
    }

    protected function matchAppCode($appId, $appName)
    {
        return $this->getMediaAppList()[$appId]['app_code'] ?? ($this->mediaApp[$appId] ?? ($this->appCode($appName)));
    }
}