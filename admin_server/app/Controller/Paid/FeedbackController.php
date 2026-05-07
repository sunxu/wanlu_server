<?php

declare(strict_types=1);

namespace App\Controller\Paid;

use App\Controller\AbstractController;
use App\Service\Paid\FeedbackService;
use Hyperf\Di\Annotation\Inject;

class FeedbackController extends AbstractController
{
    /**
     * @Inject
     * @var FeedbackService
     */
    protected $feedbackService;

    public function index()
    {
        $params = $this->request->all();
        $list = $this->feedbackService->list($params);
        return $this->responsePaginator($list->items(), $list);
    }
}