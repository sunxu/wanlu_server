<?php

declare(strict_types=1);

namespace App\Resource\Launch\LaunchAd;

use Hyperf\Resource\Json\JsonResource;

class ProjectKeepCount extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'install_num' => round(data_get($this, 'install_num'), 2),
            'activate_num' => round(data_get($this, 'activate_num'), 2),
            'active' => round(data_get($this, 'active'), 2),
            'keep1_rate' => (data_get($this, 'ago1_num') <= 0 ?
                    0 : round(data_get($this, 'keep1_num') / data_get($this, 'ago1_num') * 100, 4)) . '%',
            'keep2_rate' => (data_get($this, 'ago2_num') <= 0 ?
                    0 : round(data_get($this, 'keep2_num') / data_get($this, 'ago2_num') * 100, 4)) . '%',
            'keep3_rate' => (data_get($this, 'ago3_num') <= 0 ?
                    0 : round(data_get($this, 'keep3_num') / data_get($this, 'ago3_num') * 100, 4)) . '%',
            'keep4_rate' => (data_get($this, 'ago4_num') <= 0 ?
                    0 : round(data_get($this, 'keep4_num') / data_get($this, 'ago4_num') * 100, 4)) . '%',
            'keep5_rate' => (data_get($this, 'ago5_num') <= 0 ?
                    0 : round(data_get($this, 'keep5_num') / data_get($this, 'ago5_num') * 100, 4)) . '%',
            'keep6_rate' => (data_get($this, 'ago6_num') <= 0 ?
                    0 : round(data_get($this, 'keep6_num') / data_get($this, 'ago6_num') * 100, 4)) . '%',
            'keep7_rate' => (data_get($this, 'ago7_num') <= 0 ?
                    0 : round(data_get($this, 'keep7_num') / data_get($this, 'ago7_num') * 100, 4)) . '%',
            'recovery0_rate' => (data_get($this, 'cost0') <= 0 ?
                    0 : round(data_get($this, 'revenue0') / data_get($this, 'cost0') * 100, 4)) . '%',
            'recovery1_rate' => (data_get($this, 'cost1') <= 0 ?
                    0 : round(data_get($this, 'revenue1') / data_get($this, 'cost1') * 100, 4)) . '%',
            'recovery2_rate' => (data_get($this, 'cost2') <= 0 ?
                    0 : round(data_get($this, 'revenue2') / data_get($this, 'cost2') * 100, 4)) . '%',
            'recovery3_rate' => (data_get($this, 'cost3') <= 0 ?
                    0 : round(data_get($this, 'revenue3') / data_get($this, 'cost3') * 100, 4)) . '%',
            'recovery4_rate' => (data_get($this, 'cost4') <= 0 ?
                    0 : round(data_get($this, 'revenue4') / data_get($this, 'cost4') * 100, 4)) . '%',
            'recovery5_rate' => (data_get($this, 'cost5') <= 0 ?
                    0 : round(data_get($this, 'revenue5') / data_get($this, 'cost5') * 100, 4)) . '%',
            'recovery6_rate' => (data_get($this, 'cost6') <= 0 ?
                    0 : round(data_get($this, 'revenue6') / data_get($this, 'cost6') * 100, 4)) . '%',
            'recovery7_rate' => (data_get($this, 'cost7') <= 0 ?
                    0 : round(data_get($this, 'revenue7') / data_get($this, 'cost7') * 100, 4)) . '%',
        ];
    }
}