<?php

declare(strict_types=1);

namespace App\Resource\Launch\LaunchAd;

use Hyperf\Resource\Json\JsonResource;

class AdvertiserKeep extends JsonResource
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
            'install_num' => round(data_get($this, 'install_num'), 2),
            'activate_num' => round(data_get($this, 'activate_num'), 2),
            'active' => round(data_get($this, 'active'), 2),
            'keep1_rate' => data_get($this, 'install_keep.keep1.keep_rate', '0%'),
            'keep2_rate' => data_get($this, 'install_keep.keep2.keep_rate', '0%'),
            'keep3_rate' => data_get($this, 'install_keep.keep3.keep_rate', '0%'),
            'keep4_rate' => data_get($this, 'install_keep.keep4.keep_rate', '0%'),
            'keep5_rate' => data_get($this, 'install_keep.keep5.keep_rate', '0%'),
            'keep6_rate' => data_get($this, 'install_keep.keep6.keep_rate', '0%'),
            'keep7_rate' => data_get($this, 'install_keep.keep7.keep_rate', '0%'),
            'recovery0_rate' => data_get($this, 'recovery.recovery0.recovery_rate', '0%'),
            'recovery1_rate' => data_get($this, 'recovery.recovery1.recovery_rate', '0%'),
            'recovery2_rate' => data_get($this, 'recovery.recovery2.recovery_rate', '0%'),
            'recovery3_rate' => data_get($this, 'recovery.recovery3.recovery_rate', '0%'),
            'recovery4_rate' => data_get($this, 'recovery.recovery4.recovery_rate', '0%'),
            'recovery5_rate' => data_get($this, 'recovery.recovery5.recovery_rate', '0%'),
            'recovery6_rate' => data_get($this, 'recovery.recovery6.recovery_rate', '0%'),
            'recovery7_rate' => data_get($this, 'recovery.recovery7.recovery_rate', '0%'),
        ];
    }
}