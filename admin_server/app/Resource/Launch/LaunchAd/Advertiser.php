<?php

declare(strict_types=1);

namespace App\Resource\Launch\LaunchAd;

use Hyperf\Resource\Json\JsonResource;

class Advertiser extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'date' => data_get($this, 'date'),
            'advertiser_id' => data_get($this, 'advertiser_id'),
            'advertiser_name' => data_get($this, 'advertiser_name'),
            'agency_name' => data_get($this, 'agency_name'),
            'cost' => round(data_get($this, 'cost'), 2),
            'before_budget' => round(data_get($this, 'before_budget'), 2),
            'budget' => round(data_get($this, 'budget'), 2),
            'budget_finish' => round(data_get($this, 'budget') <= 0 ? 0 :data_get($this, 'cost') / data_get($this, 'budget') * 100, 2) . '%',
            'install_payment' => round(data_get($this, 'install_payment'), 2),
            'withdraw' => round(data_get($this, 'withdraw'), 2),
            'install_num' => data_get($this, 'install_num') ?? 0,
            'install_pre_cost' => data_get($this, 'install_num') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'install_num'), 2),
            'install_withdraw' => data_get($this, 'install_num') <= 0 ?
                0 : round((data_get($this, 'cost') + data_get($this, 'withdraw')) / data_get($this, 'install_num'), 2),
            'install_avg_revenue' => data_get($this, 'install_avg_revenue') ?? 0,
            'install_roi' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / data_get($this, 'cost'), 2),
            'install_roi_withdraw' => data_get($this, 'cost') + data_get($this, 'withdraw') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / (data_get($this, 'cost') + data_get($this, 'withdraw')), 2),
            'install_roi_payment' => data_get($this, 'cost') - data_get($this, 'install_payment') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / (data_get($this, 'cost') - data_get($this, 'install_payment')), 2),
            'install_roi_24h' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue24') / data_get($this, 'cost'), 2),
            'install_roi_24h_withdraw' => data_get($this, 'cost') + data_get($this, 'withdraw') <= 0 ?
                0 : round(data_get($this, 'install_revenue24') / (data_get($this, 'cost') + data_get($this, 'withdraw')), 2),
            'active_cost' => data_get($this, 'active_cost'),
            'key_action_cost' => data_get($this, 'key_action_cost'),
            'key_action_rate' => data_get($this, 'key_action_rate', 0) . '%',
            'active' => data_get($this, 'active'),
            'key_action' => data_get($this, 'key_action'),
            'recovery3_rate' => data_get($this, 'recovery.recovery3.recovery_rate', '0%'),
            'recovery7_rate' => data_get($this, 'recovery.recovery7.recovery_rate', '0%'),
            'recovery10_rate' => data_get($this, 'recovery.recovery10.recovery_rate', '0%'),
            'keep1_rate' => data_get($this, 'activate_keep.keep1.keep_rate', '0%'),
            'keep3_rate' => data_get($this, 'activate_keep.keep3.keep_rate', '0%'),
            'keep7_rate' => data_get($this, 'activate_keep.keep7.keep_rate', '0%'),
            'keep10_rate' => data_get($this, 'activate_keep.keep10.keep_rate', '0%'),
        ];
    }
}