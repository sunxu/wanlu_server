<?php

declare(strict_types=1);

namespace App\Controller\Media;

use App\Controller\AbstractController;
use App\Service\Media\TotalService;
use Hyperf\Di\Annotation\Inject;

class TotalController extends AbstractController
{
    /**
     * @Inject
     * @var TotalService
     */
    protected $totalService;

    public function app()
    {
        $params = $this->request->all();
        return $this->responseSuccess($this->totalService->app($params));
    }

    public function company()
    {
        $params = $this->request->all();
        return $this->responseSuccess($this->totalService->company($params));
    }
}