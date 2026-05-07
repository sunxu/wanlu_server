<?php

declare(strict_types=1);

namespace App\Controller\Cash;

use App\Controller\AbstractController;
use App\Service\Cash\EcpmService;
use Hyperf\Di\Annotation\Inject;

class EcpmController extends AbstractController
{
    /**
     * @Inject
     * @var EcpmService
     */
    protected $ecpmService;

    public function index(string $appCode)
    {
        $params = $this->request->all();
        $result = $this->ecpmService->ecpmList($appCode, $params);

        return $this->responseRecord($result);
    }
}