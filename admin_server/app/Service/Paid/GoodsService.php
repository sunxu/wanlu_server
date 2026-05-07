<?php

declare(strict_types=1);

namespace App\Service\Paid;

use App\ModelTools\PaidGoods;

class GoodsService
{
    public function goodsList(array $params)
    {
        return PaidGoods::when($goodsNo = data_get($params, 'goods_no'), function ($query) use ($goodsNo) {
                $query->where('goods_no', $goodsNo);
            })
            ->when($goodsName = data_get($params, 'goods_name'), function ($query) use ($goodsName) {
                $query->where('goods_name', 'like', "%{$goodsName}%");
            })
            ->orderBy('updated_time', 'desc')
            ->orderBy('goods_no', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)), ['*'], 'page', intval(data_get($params, 'page', 1)));
    }

    public function store(array $params)
    {
        $model = new PaidGoods();
        $model->fill($params);
        $model->saveOrFail();
    }

    public function edit(int $goodsNo, array $params)
    {
        $model = PaidGoods::findOrFail($goodsNo);
        $model->fill($params);
        $model->saveOrFail();
    }

    public function enable(int $goodsNo)
    {
        $model = PaidGoods::findOrFail($goodsNo);
        $model->is_enable ^= 1 ^ 0;
        $model->saveOrFail();
    }
}