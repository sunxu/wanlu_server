<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Model\AppCompany;
use App\Service\AdSlot\AdSlotService;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

/**
 * @Command
 */
class MediaApp extends HyperfCommand
{
    protected $name = 'media:app';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("拉去media app");
    }

    public function handle()
    {
        $companyLIst = AppCompany::select(['company_id', 'media_conf_csj', 'media_conf_ylh', 'media_conf_ks'])
            ->where('status', 1)->get()->all();
        make(AdSlotService::class)->refreshApp($companyLIst);
    }
}