<?php

namespace App\Service\Mkt;

use App\Model\Mkt\MktAdvertiser;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Utils\Ding;
use App\Utils\Http;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

class HonorService
{
    protected $baseUri = 'https://ads.hihonor.com';
    protected $csrfTokenUri = '/api/ad-delivery/v1/ad-csrf-token';
    protected $refererUri = '/delivery/index.html';
    protected $dataUri = '/api/ad-delivery/v1/report/adCampaign';
    protected $csrfToken = [];

    /**
     * @param string $appCode
     * @param string $date
     */
    public function handle(string $appCode, string $date)
    {
        try {
            $this->updateData($appCode, $date);
        } catch (Exception $e) {
            $this->pushMessage($e->getMessage());
        }
    }

    /**
     * @param string $appCode
     * @param string $date
     * @throws Exception
     */
    protected function updateData(string $appCode, string $date)
    {
        $advertiser = MktAdvertiser::query()
            ->where('app_code', $appCode)
            ->where('platform', 'honor')
            ->where('status', 1)
            ->select([
                'advertiser_id',
                'extra',
            ])
            ->get()
            ->toArray();
        foreach ($advertiser as $v) {
            $data = $this->getPutData($v['advertiser_id'], $date, $v['extra']);
            if (!empty($data)) {
                ReportDailyAdvertiser::updateOrCreate([
                    'date' => $date,
                    'advertiser_id' => $v['advertiser_id'],
                ], [
                    'app_code' => $appCode,
                    'platform' => 'honor',
                    'cost' => $data['cost'] ?? 0,
                    'show' => $data['show'] ?? 0,
                    'click' => $data['click'] ?? 0
                ]);
            }
        }
    }

    /**
     * @param string $advertiserId
     * @param string $date
     * @param array $extra
     * @return array
     * @throws Exception
     */
    protected function getPutData(string $advertiserId, string $date, array $extra)
    {
        try {
            $response = $this->getClient()->request("POST", $this->dataUri, [
                'json' => [
                    'advertiserId' => $advertiserId,
                    'endTime' => date('Y-m-d 23:59:59', strtotime($date)),
                    'startTime' => date('Y-m-d 00:00:00', strtotime($date)),
                    'pageIndex' => 1,
                    'pageSize' => 1000,
                    'isQryAll' => 0,
                ],
                'headers' => [
                    'ad-advertiser-id' => $advertiserId,
                    'Referer' => $this->baseUri . $this->refererUri,
                    'x-ad-token' => $extra['token'] ?? '',
                    'x-csrf-token' => $this->getCsrfToken($advertiserId, $extra),
                    'Cookie' => 'x-account-id=' . $extra['account_id'] ?? ''
                ]
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $contents = json_decode($response->getBody()->getContents(), true);
        if (($contents['code'] ?? -1) !== 0) {
            throw new Exception($contents['message'] ?? $response->getBody()->getContents());
        }
        if (($contents['data']['adPageResponse']['code'] ?? -1) !== 0) {
            throw new Exception($contents['data']['adPageResponse']['message'] ?? json_encode($contents['data']['adPageResponse']));
        }
        $data = [
            'cost' => 0,
            'show' => 0,
            'click' => 0,
        ];
        foreach ($contents['data']['adPageResponse']['data'] ?? [] as $v) {
            $cost = $v['adBilling'] / 1000000;
            if ($cost > 0) {
                $data = [
                    'cost' => round($data['cost'] + $v['adBilling'] / 1000000, 2),
                    'show' => $data['show'] + $v['impressionCount'],
                    'click' => $data['click'] + $v['clickCount']
                ];
            }

        }
        return $data['cost'] > 0 ? $data : [];
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getCsrfToken(string $advertierId, array $extra)
    {
        if (!empty($this->csrfToken[$advertierId])) {
            return $this->csrfToken[$advertierId];
        }
        try {
            $response = $this->getClient()->request('GET', $this->csrfTokenUri, [
                'headers' => [
                    'Referer' => $this->baseUri . $this->refererUri,
                    'Cookie' => 'x-account-id=' . $extra['account_id'] ?? ''
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new Exception($e->getMessage());
        }
        $contents = json_decode($response->getBody()->getContents(), true);
        if (($contents['code'] ?? -1) !== 0) {
            throw new Exception(json_encode($contents, 320));
        }
        $token = $contents['data']['token'] ?? '';
        if (empty($token)) {
            throw new Exception('csrfToken 获取失败');
        }
        $this->csrfToken[$advertierId] = $token;
        return $this->csrfToken[$advertierId];
    }

    protected function getClient()
    {
        return make(Http::class)->get([
            'base_uri' => $this->baseUri
        ]);
    }

    protected function pushMessage(string $text)
    {
        make(Ding::class, ['warning'])->pushText($text);
    }
}