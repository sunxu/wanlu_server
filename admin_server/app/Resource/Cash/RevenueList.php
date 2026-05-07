<?php

declare(strict_types=1);

namespace App\Resource\Cash;

use Hyperf\Resource\Json\JsonResource;

class RevenueList extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        return [
            'date' => data_get($this, 'date'),
            'cost' => data_get($this, 'cost'),
            'installed_num' => data_get($this, 'installed_num'),

            'revenue_rate_0' => $this->getRevenueRate(),
            'revenue_rate_1' => $this->getRevenueRate(1),
            'revenue_rate_2' => $this->getRevenueRate(2),
            'revenue_rate_3' => $this->getRevenueRate(3),
            'revenue_rate_4' => $this->getRevenueRate(4),
            'revenue_rate_5' => $this->getRevenueRate(5),
            'revenue_rate_6' => $this->getRevenueRate(6),
            'revenue_rate_7' => $this->getRevenueRate(7),
            'revenue_rate_14' => $this->getRevenueRate(14),
            'revenue_rate_30' => $this->getRevenueRate(30),
        ];
    }

    public function getRevenueRate($day = 0)
    {
        $cost = data_get($this, 'cost');
        if(!$cost || $cost <= 0){
            return "0%";
        }
        if($day > 0 && data_get($this, "installed_revenue_{$day}", 0) <= 0){
            return "0%";
        }
        $r = 0;
        for (; $day > 0; $day--){
            $r += data_get($this, "installed_revenue_{$day}", 0);
        }
        $r += data_get($this, "installed_revenue", 0);
        if($r <= 0){
            return "0%";
        }
        return round($r / $cost * 100, 2) . "%";
    }
}