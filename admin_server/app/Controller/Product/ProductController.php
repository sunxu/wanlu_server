<?php

declare(strict_types=1);

namespace App\Controller\Product;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Service\Placement\LaunchBaseService;
use App\Service\Product\ProductService;
use Hyperf\Di\Annotation\Inject;

class ProductController extends AbstractController
{
    /**
     * @Inject
     * @var ProductService
     */
    protected $productService;

    public function deviceEcpmEventList(string $appCode)
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $list = $this->productService->deviceEcpmEventList($appCode, $params);

        return $this->responseRecord($list);
    }

    public function deviceEcpmEventCondition(string $appCode)
    {
        $condition = $this->productService->deviceEcpmEventCondition();

        return $this->responseSuccess($condition);
    }

    /**
     * @Inject
     * @var LaunchBaseService
     */
    protected $launchBaseService;

    public function dailyBase()
    {
        $params = $this->request->all();
        $result = $this->launchBaseService->list($params);
        return $this->responsePaginator($result->items(), $result);
    }
}