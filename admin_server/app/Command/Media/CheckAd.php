<?php

declare(strict_types=1);

namespace App\Command\Media;

use App\Utils\Ding;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class CheckAd extends HyperfCommand
{
    protected $name = 'media:checkAd';

    protected function getArguments()
    {
        return [
            ['media', InputArgument::IS_ARRAY, '媒体', []],
        ];
    }

    public function handle()
    {
        $list = Db::table('media_ad as ad')
            ->select([
                'ad.media_type',
                'mapp.app_name',
                'ad.placement_id',
                'ad.placement_name',
                'ad.ecpm',
                'ad.bidding'
            ])
            ->leftJoin('app', 'app.app_code', '=', 'ad.app_code')
            ->leftJoin('app_media_app as mapp', 'mapp.app_id', '=', 'ad.app_id')
            ->where('ad.status', '=', 1)
            ->where('mapp.status', '=', 1)
            ->where('app.ad_status', '=', 1)
//            ->where('ad.ecpm', '=', 0)
            ->get()
            ->toArray();

        $warning = [];
        foreach ($list as $ad) {
            $ad = (array)$ad;
            if (!$this->check($ad)) {
                $warning[$ad['media_type']][$ad['app_name']][] = [
                    'ID' => $ad['placement_id'],
                    'Name' => $ad['placement_name'],
                    'Ecpm' => $ad['ecpm']
                ];
            }
        }

        $this->push($warning);
    }

    protected function check(array $ad)
    {
        if ($ad['bidding'] || strstr($ad['placement_name'], ':')) {
            return true;
        }

        $name = str_replace('-', '_', $ad['placement_name'] ?? '');
        $arr = explode('_', $name);

        foreach ($arr as $item) {
            if (is_numeric($item) && $item >= 0 && $item <= 10000 && $item != $ad['ecpm']) {
                return false;
            }
        }
        return true;
    }

    protected function push(array $list)
    {
        if (empty($list)) {
            return;
        }

        $message = '**代码位告警**';
        $message .= PHP_EOL . PHP_EOL;
        foreach ($list as $media => $item) {
            $message .= '---';
            $message .= PHP_EOL . PHP_EOL;
            $message .= "**媒体：{$media}** " . PHP_EOL;
            $message .= '---';
            $message .= PHP_EOL . PHP_EOL;
            foreach ($item as $app => $adList) {
                $message .= '---';
                $message .= PHP_EOL . PHP_EOL;
                $message .= "**应用：{$app}** ";
                foreach ($adList as $d) {
                    foreach ($d as $key => $value) {
                        $message .= PHP_EOL . PHP_EOL;
                        $message .= "**{$key}：** " . $value;
                    }
                    $message .= PHP_EOL . PHP_EOL;
                }
            }
        }

        make(Ding::class)->setRobotCode('media_ad')->pushMarkdown('警报', $message);
    }
}
