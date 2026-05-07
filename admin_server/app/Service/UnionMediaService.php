<?php

namespace App\Service;

use App\Constants\CsjConst;
use App\Library\Excel\ReaderExcel;
use App\Library\HttpClient\HttpClient;
use App\Model\AppCompany;
use App\Model\PlacementCsj;
use App\Model\PlacementCsjNetwork;

class UnionMediaService
{
    public function bindCsj(int $companyId, string $appCode, array $params)
    {
        $data = [];
        foreach ($params as $item){
            $adUnitId = data_get($item, 'ad_unit_id');
            $adSlotList = isset($data[$adUnitId])&&isset($data[$adUnitId]['ad_slot_list'])?$data[$adUnitId]['ad_slot_list']:[];
            unset($item['ad_unit_id']);
            foreach ($item as $key => $value){
                if($value !== 0 && empty($value)){
                    unset($item[$key]);
                }
            }
            $item['ad_slot_id'] = strval($item['ad_slot_id']);
            $item['bidding_type'] = intval($item['bidding_type']);
            $item['status'] = intval($item['status']);
            $item['origin_type'] = intval($item['origin_type']);
            $item['smart_sort_switch'] = intval($item['smart_sort_switch']);
            if(isset($item['price'])){
                $item['price'] = floatval($item['price']);
            }
            if(isset($item['sort_type'])){
                $item['sort_type'] = intval($item['sort_type']);
            }
            if(isset($item['sub_ad_type'])){
                $item['sub_ad_type'] = intval($item['sub_ad_type']);
            }
            array_push($adSlotList, $item);
            $data[$adUnitId]['ad_unit_id'] = intval($adUnitId);
            $data[$adUnitId]['ad_slot_list'] = $adSlotList;
        }
        $result = [
            'success' => [],
            'failed' => [],
        ];
        $networks = $this->networkCsj($companyId);
        foreach ($data as $key => $value){
            $value['ad_slot_list'] = json_encode($value['ad_slot_list'], JSON_UNESCAPED_UNICODE + JSON_UNESCAPED_SLASHES);
            $res = HttpClient::connect('Csj', 'Waterfall')->setAccount($companyId)->batchAdslot($value);
            if($res['code'] == 100){
                $result['success'][] = [
                    'ad_unit_id' => $key,
                    'message' => '成功'
                ];
                $this->saveBindCsj($companyId, $appCode, $value, $networks);
            }else{
                $result['failed'][] = [
                    'ad_unit_id' => $key,
                    'message' => $res['message']
                ];
            }
        }
        return $result;
    }

    public function saveBindCsj(int $companyId, string $appCode, array $params, array $networks)
    {
        $adUnitId = data_get($params, 'ad_unit_id');
        $adSlotList = json_decode(data_get($params, 'ad_slot_list'), true);
        foreach ($adSlotList as $item){
            data_set($item, 'ad_unit_id', $adUnitId);
            data_set($item, 'app_code', $appCode);
            data_set($item, 'company_id', $companyId);
            data_set($item, 'created_time', time());
            $networkName = collect($networks)->where('network_id', data_get($item, 'network'))->pluck('network_name')->first();
            data_set($item, 'network_name', $networkName);
            PlacementCsj::insert($item);
        }
    }

    public function bindListCsj(string $appCode, array $params)
    {
        return PlacementCsj::where('app_code', $appCode)
            ->orderBy('created_time', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));
    }

    public function readerCsjExcel(string $filePath)
    {
        $excel = make(ReaderExcel::class, [$filePath]);
        $excel->setMaxColumn(12);
        $excel->setReplaceColumn([
            'A' => 'ad_unit_id',
            'B' => 'bidding_type',
            'C' => 'ad_slot_id',
            'D' => 'ad_slot_name',
            'E' => 'network',
            'F' => 'price',
            'G' => 'sort_type',
            'H' => 'status',
            'I' => 'origin_type',
            'J' => 'sub_ad_type',
            'K' => 'smart_sort_switch',
            'L' => 'smart_sort_fetch_days',
        ]);
        return $excel->readExcel();
    }

    public function formatCsjExcelData(int $companyId, array $excelData)
    {
        $networks = [];
        $definedNetworks = PlacementCsjNetwork::where('company_id', $companyId)->get()->all();
        foreach ($definedNetworks as $network){
            $networks[strtolower(data_get($network, 'network_name'))] = data_get($network, 'network_id');
        }
        $networks = array_merge($networks, CsjConst::NETWORK);
        $data = collect($excelData)->map(function ($item) use($networks) {
            // 竞价类型
            $biddingType = trim(data_get($item, 'bidding_type'));
            $biddingTypeValue = $biddingType && isset(CsjConst::BIDDING_TYPE[$biddingType]) ? CsjConst::BIDDING_TYPE[$biddingType] :CsjConst::BIDDING_TYPE_COMMON;
            data_set($item, 'bidding_type', $biddingTypeValue);
            // 广告网络
            $network = strtolower(trim(data_get($item, 'network')));
            $networkValue = $network && isset($networks[$network]) ? $networks[$network] :'';
            data_set($item, 'network', $networkValue);
            // 排序方式
            $sortType = trim(data_get($item, 'sort_type'));
            $sortTypeValue = $sortType && isset(CsjConst::SORT_TYPE[$sortType]) ? CsjConst::SORT_TYPE[$sortType] :'';
            data_set($item, 'sort_type', $sortTypeValue);
            // 状态
            $status = trim(data_get($item, 'status'));
            $statusValue = $status && isset(CsjConst::STATUS[$status]) ? CsjConst::STATUS[$status] : CsjConst::STATUS_OPEN;
            data_set($item, 'status', $statusValue);
            // 渲染类型
            $originType = trim(data_get($item, 'origin_type'));
            $originTypeValue = $originType && isset(CsjConst::ORIGIN_TYPE[$originType]) ? CsjConst::ORIGIN_TYPE[$originType] : CsjConst::ORIGIN_TYPE_NONE;
            data_set($item, 'origin_type', $originTypeValue);
            // 代码位类型
            $subAdType = trim(data_get($item, 'sub_ad_type'));
            $subAdTypeValue = $subAdType && isset(CsjConst::SUB_AD_TYPE[$subAdType]) ? CsjConst::SUB_AD_TYPE[$subAdType] :'' ;
            data_set($item, 'sub_ad_type', $subAdTypeValue);
            // 自动排序开关
            $smartSortSwitch = trim(data_get($item, 'smart_sort_switch'));
            $smartSortSwitchValue = $smartSortSwitch && isset(CsjConst::SMART_SORT_SWITCH[$smartSortSwitch]) ? CsjConst::SMART_SORT_SWITCH[$smartSortSwitch] : CsjConst::SMART_SORT_SWITCH_NONE;
            data_set($item, 'smart_sort_switch', $smartSortSwitchValue);

            return $item;
        })->toArray();
        return $data;
    }

    public function networkCsj(int $companyId)
    {
        $networks = [];
        $definedNetworks = PlacementCsjNetwork::where('company_id', $companyId)->get()->all();
        foreach ($definedNetworks as $network){
            $networks[] = [
                'network_name' => strtolower(data_get($network, 'network_name')),
                'network_id' => data_get($network, 'network_id')
            ];
        }
        foreach (CsjConst::NETWORK as $key => $network){
            $networks[] = [
                'network_name' => $key,
                'network_id' => $network
            ];
        }
        return $networks;
    }

    public function accountCsj()
    {
        return AppCompany::select(['company_id', 'company_name'])
            ->whereNotNull('media_conf_csj')->get()->all();
    }
}