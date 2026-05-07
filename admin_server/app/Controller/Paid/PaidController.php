<?php

declare(strict_types=1);

namespace App\Controller\Paid;

use App\Controller\AbstractController;
use App\Service\Paid\GoodsService;
use App\Service\Paid\PaidService;
use Hyperf\Di\Annotation\Inject;

class PaidController extends AbstractController
{
    /**
     * @Inject
     * @var PaidService
     */
    protected $paidService;

    /**
     * @Inject
     * @var GoodsService
     */
    protected $goodsService;

    public function reportDailyList()
    {
        $list = $this->paidService->reportDailyList($this->request->all());
        $goodsList = $this->goodsService->goodsList(['per_page' => 1000, 'page' => 1]);

        return $this->responseSuccess([
            'goods' => collect(object_to_arr($goodsList->items()))->map(function ($item) {
                return [
                    'goods_no' => data_get($item, 'goods_no'),
                    'goods_name' => data_get($item, 'goods_name'),
                ];
            })->all(),
            'records' => $list->items(),
            'current' => $list->currentPage(),
            'page_size' => $list->count(),
            'total' => $list->total(),
        ]);
    }
}