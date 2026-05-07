<?php

declare(strict_types=1);

namespace App\Controller\Paid;

use App\Controller\AbstractController;
use App\Service\Paid\GoodsService;
use Hyperf\Di\Annotation\Inject;

class GoodsController extends AbstractController
{
    /**
     * @Inject
     * @var GoodsService
     */
    protected $goodsService;

    public function index()
    {
        $params = $this->request->all();
        $list = $this->goodsService->goodsList($params);
        return $this->responsePaginator($list->items(), $list);
    }

    public function store()
    {
        $params = $this->request->all();
        $this->goodsService->store($params);

        return $this->responseSuccess();

    }

    public function edit(int $goodsNo)
    {
        $params = $this->request->all();
        $this->goodsService->edit($goodsNo, $params);

        return $this->responseSuccess();
    }

    public function enable(int $goodsNo)
    {
        $this->goodsService->enable($goodsNo);

        return $this->responseSuccess();
    }
}