<?php

declare(strict_types=1);

namespace App\Controller\Cash;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Service\Cash\ShowService;
use Hyperf\Di\Annotation\Inject;

class ShowController extends AbstractController
{
    /**
     * @Inject
     * @var ShowService
     */
    protected $showService;

    public function index(string $appCode, string $type)
    {
        $params = $this->request->all();
        if (strtotime(data_get($params, 'date_end')) - strtotime(data_get($params, 'date_begin')) > 60 * 60 * 24 * 30) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '查询日期应在 30 天以内');
        }
        $data = $this->showService->showList($appCode, $type, $params);

        return $this->responseRecord($data);
    }
}