<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Constants\MediaConst;
use App\Model\AppCompany;
use App\Service\Media\MediaService;
use App\Utils\Ding;
use Exception;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class GetIncome extends HyperfCommand
{
    protected $name = 'media:getIncome';

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d')],
            ['media', InputArgument::IS_ARRAY, '媒体', []],
        ];
    }

    public function handle()
    {
        $args = $this->input->getArguments();

        $date = data_get($args, 'date');
        $mediaArr = data_get($args, 'media');

        // 日期
        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
        ];

        $date = $dateArr[$date] ?? $date;

        $companyList = $this->getCompanyList();

        foreach ($companyList as $company) {

            foreach (MediaConst::MEDIA_TYPE as $media => $mediaName) {

                if (empty($company["media_conf_{$media}"]) || (!empty($mediaArr) && !in_array($media, $mediaArr))) {
                    continue;
                }
                go(function () use ($media, $company, $date) {
                    try {
                        make(MediaService::class, [
                            (string)$media,
                            (array)$company["media_conf_{$media}"],
                            [
                                'media_app' => isset($company["media_app_{$media}"]) ? (array)$company["media_app_{$media}"] : [],
                                'company_id' => $company['company_id']
                            ]
                        ])->income((string)$date);
                    } catch (Exception $e) {
                        make(Ding::class, ['warning'])->pushText(json_encode([$company['company_name'] ?? '', $media, $e->getMessage()], JSON_UNESCAPED_UNICODE));
                    }
                });
            };
        }
    }

    public function getCompanyList()
    {
        return AppCompany::query()
            ->where('status', 1)
            ->get()
            ->toArray();
    }
}
