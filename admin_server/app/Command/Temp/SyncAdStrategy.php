<?php

declare(strict_types=1);

namespace App\Command\Temp;

use App\Constants\AppConst;
use App\Model\AdStrategy as AdStrategyUc;
use App\ModelMis\AdStrategy as AdStrategyMis;
use App\ModelTools\AdConfig as AdStrategyTools;
use App\ModelTools\App as AppTools;
use App\Model\App as AppUc;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;

/**
 * @Command
 */
class SyncAdStrategy extends HyperfCommand
{
    protected $name = 'temp:SyncAdStrategy';

    protected function configure()
    {
        parent::configure();
        $this->setDescription("临时脚本:同步广告策略");
    }

    public function handle()
    {
        /*同步工具表*/
        AdStrategyTools::query()->get()->map(function ($row) {
            var_dump($row->app_id);
            $App = AppTools::find($row->app_id);
            if ($App) {
                var_dump($App->appcode);
                echo PHP_EOL;
                $source = array_flip(AppConst::SOURCE_OLD_MAP)[$row['source']];
                $source = $row['union'] == 1 ? AppConst::SOURCE_LY : $source;
                $launch_type = $row['account'] == 1 ? AppConst::LAUNCH_TYPE_GJXW : AppConst::LAUNCH_TYPE_CLSC;
                $source = [$source];
                $launch_type = [$launch_type];
                $key = json_decode($row->key);
                $name = $this->createName($App->appname, $source, $launch_type, $key);
                $data = [];
                $data['name'] = $name;
                $data['app_code'] = $App->appcode;
                $data['channel'] = $row->channel;
                $data['key'] = json_encode($key);
                $data['vendor'] = $row->vendor;
                $data['source'] = json_encode($source);
                $data['launch_type'] = json_encode($launch_type);
                $data['info'] = $row->info;
                (new AdStrategyUc())->fill($data)->save();
            }
        });

        /*同步网赚表*/
        AdStrategyMis::query()->get()->map(function ($row) {
            var_dump($row->app_id);
            $App = AppUc::find($row->app_id);
            if ($App) {
                var_dump($App->app_code);
                echo PHP_EOL;
                $source = json_decode($row['source']);
                $source = $row['is_union'] == 1 ? [AppConst::SOURCE_LY] : $source;
                $launch_type = $row['is_action_account'] == 1 ? AppConst::LAUNCH_TYPE_GJXW : AppConst::LAUNCH_TYPE_CLSC;
                $launch_type = [$launch_type];
                $key = json_decode($row->key);
                $name = $this->createName($App->app_name, $source, $launch_type, $key);
                $data = [];
                $data['name'] = $name;
                $data['app_code'] = $App->app_code;
                $data['channel'] = $row->channel;
                $data['key'] = json_encode($key);
                $data['vendor'] = $row->vendor;
                $data['source'] = json_encode($source);
                $data['launch_type'] = json_encode($launch_type);
                $data['info'] = $row->info;
                (new AdStrategyUc())->fill($data)->save();
            }
        });

    }

    public function createName($app_name, $source, $launch_type, $key)
    {
        $source_name = '';
        foreach ($source as $row) {
            $source_name .= AppConst::SOURCE_NAME_MAP[$row] . ',';
        }
        $source_name = trim($source_name, ',');

        $launch_type_name = '';
        foreach ($launch_type as $row) {
            $launch_type_name .= AppConst::LAUNCH_TYPE_NAME_MAP[$row] . ',';
        }
        $launch_type_name = trim($launch_type_name, ',');

        $key_name = '';
        foreach ($key as $row) {
            $key_name .= AppConst::STRATEGY_KEY_NAME_MAP[$row] . ',';
        }
        $key_name = trim($key_name, ',');

        return $app_name . '-' . $source_name . '-' . $launch_type_name . '-' . $key_name;

    }
}