<?php

declare(strict_types=1);

namespace App\Resource\Launch\LaunchAd;

use Hyperf\Resource\Json\JsonResource;

class ProjectCount extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'cost' => round(data_get($this, 'cost'), 2),
            'install_payment' => round(data_get($this, 'install_payment'), 2),
            'withdraw' => round(data_get($this, 'withdraw'), 2),
            'install_num' => data_get($this, 'install_num'),
            'install_pre_cost' => data_get($this, 'install_num') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'install_num'), 2),
            'install_withdraw' => data_get($this, 'install_num') <= 0 ?
                0 : round((data_get($this, 'cost') + data_get($this, 'withdraw')) / data_get($this, 'install_num'), 2),
            'install_avg_revenue' => data_get($this, 'install_num') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / data_get($this, 'install_num'), 2),
            'install_roi' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / data_get($this, 'cost'), 2),
            'install_roi_withdraw' => data_get($this, 'cost') + data_get($this, 'withdraw') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / (data_get($this, 'cost') + data_get($this, 'withdraw')), 2),
            'install_roi_24h' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue24') / data_get($this, 'cost'), 2),
            'install_roi_24h_withdraw' => data_get($this, 'cost') + data_get($this, 'withdraw') <= 0 ?
                0 : round(data_get($this, 'install_revenue24') / (data_get($this, 'cost') + data_get($this, 'withdraw')), 2),
            'active_cost' => data_get($this, 'active') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'active'), 2),
            'key_action_cost' => data_get($this, 'key_action') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'key_action'), 2),
            'key_action_rate' => (data_get($this, 'active') <= 0 ?
                0 : round(data_get($this, 'key_action') * 100 / data_get($this, 'active'), 2)) . '%',
            'active' => data_get($this, 'active'),
            'key_action' => data_get($this, 'key_action'),
            'recovery3_rate' => (data_get($this, 'cost3') <= 0 ?
                0 : round(data_get($this, 'revenue3') / data_get($this, 'cost3') * 100, 4)) . '%',
            'recovery7_rate' => (data_get($this, 'cost7') <= 0 ?
                0 : round(data_get($this, 'revenue7') / data_get($this, 'cost7') * 100, 4)) . '%',
            'recovery10_rate' => (data_get($this, 'cost10') <= 0 ?
                0 : round(data_get($this, 'revenue10') / data_get($this, 'cost10') * 100, 4)) . '%',
            'keep1_rate' => (data_get($this, 'ago1_num') <= 0 ?
                0 : round(data_get($this, 'keep1_num') / data_get($this, 'ago1_num') * 100, 4)) . '%',
            'keep3_rate' => (data_get($this, 'ago3_num') <= 0 ?
                0 : round(data_get($this, 'keep3_num') / data_get($this, 'ago3_num') * 100, 4)) . '%',
            'keep7_rate' => (data_get($this, 'ago7_num') <= 0 ?
                0 : round(data_get($this, 'keep7_num') / data_get($this, 'ago7_num') * 100, 4)) . '%',
            'keep10_rate' => (data_get($this, 'ago10_num') <= 0 ?
                0 : round(data_get($this, 'keep10_num') / data_get($this, 'ago10_num') * 100, 4)) . '%',
        ];
    }
}