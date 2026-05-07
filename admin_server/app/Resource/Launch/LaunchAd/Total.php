<?php

declare(strict_types=1);

namespace App\Resource\Launch\LaunchAd;

use App\Constants\MktConst;
use Hyperf\Resource\Json\JsonResource;

class Total extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'date' => data_get($this, 'date'),
            'app_name' => data_get($this, 'app_name'),
            'cost' => round(data_get($this, 'cost'), 2),
            'install_num' => round(data_get($this, 'install_num') ?? 0),
            'install_pre_cost' => data_get($this, 'install_num') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'install_num'), 2),
            'install_roi' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / data_get($this, 'cost'), 2),
            'install_roi_24h' => data_get($this, 'cost') <= 0 ?
                0 : round(data_get($this, 'install_revenue24') / data_get($this, 'cost'), 2),
            'install_arpu' => data_get($this, 'install_num') <= 0 ?
                0 : round(data_get($this, 'install_revenue') / data_get($this, 'install_num'), 2),
            'active_cost' => data_get($this, 'active') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'active'), 2),
            'key_action_cost' => data_get($this, 'key_action') <= 0 ?
                0 : round(data_get($this, 'cost') / data_get($this, 'key_action'), 2),
            'advertiser_num' => data_get($this, 'advertiser_num') ?? 0,
        ];
    }
}