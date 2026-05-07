<?php

declare(strict_types=1);

namespace App\Command\Report;

use App\Constants\AppConst;
use App\Model\App;
use App\Utils\Ding;
use Exception;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class Guiyin extends HyperfCommand
{
    protected $name = 'report:guiyin';

    protected $dbConnection = 'tools';

    protected function getArguments()
    {
        return [
            ['date', InputArgument::OPTIONAL, '日期', date('Y-m-d')],
            ['app', InputArgument::IS_ARRAY, '应用', []],
        ];
    }

    public function handle()
    {
        $args = $this->input->getArguments();

        $date = data_get($args, 'date');
        $appArr = data_get($args, 'app');

        // 日期
        $dateArr = [
            'today' => date('Y-m-d'),
            'yesterday' => date('Y-m-d', strtotime('-1 days')),
        ];

        $date = $dateArr[$date] ?? $date;

        $appList = $this->getAppList($appArr);
        foreach ($appList as $code => $name) {
            try {
                $this->report($code, (string)$date);
            } catch (Exception $e) {
                make(Ding::class, ['warning'])->pushText(json_encode([$code, $date, $e->getMessage()], JSON_UNESCAPED_UNICODE));
            }
        };
    }

    protected function getAppList(array $appArr)
    {
        return (new App())->appList([AppConst::APP_TYPE_TOOLS], $appArr);
    }

    protected function report(string $code, string $date)
    {
        $list = Db::connection($this->dbConnection)->table("app_{$code} as a")
            ->select([
                'version',
                Db::raw("count(*) as install"),
                Db::raw("count(if(not exists (select 1 from app_{$code}_temp as b where a.android_id=b.android_id),1,null)) as install_new"),
                Db::raw("count(IF( active_time > 0, 1, null )) as active"),
                Db::raw("count(IF( active_time > 0 and not exists (select 1 from app_{$code}_temp as b where a.android_id=b.android_id), 1, null )) as active_new"),
                Db::raw("count(IF( ad_time > 0, 1, null )) as guiyin"),
                Db::raw("count(IF( ad_time > 0 and not exists (select 1 from app_{$code}_temp as b where a.android_id=b.android_id), 1, null )) as guiyin_new"),
                Db::raw("count(IF(oaid='',null,1)) as install_param"),
            ])
            ->where('create_time', '>=', strtotime($date))
            ->where('create_time', '<=', strtotime($date . ' 23:59:59'))
            ->groupBy('version')->get()->all();

        if (empty($this)) {
            return;
        }

        $black = Db::connection($this->dbConnection)->table('com_blacklist')
            ->select([
                'version',
                Db::raw("count(*) as black"),
                Db::raw("count(IF(target->'$.client_audit'=true,1,null)) as client")
            ])
            ->where('appcode', $code)
            ->where('create_time', '>=', strtotime($date))
            ->where('create_time', '<=', strtotime($date . ' 23:59:59'))
            ->groupBy('version')->get()->all();

        $jl = Db::connection($this->dbConnection)->table("app_{$code}_ad as appa")
            ->select([
                'app.version',
                Db::raw("COUNT(*) as jl_total"),
                Db::raw("COUNT(IF(appa.oaid='',null,1)) as jl_param"),
            ])
            ->where('appa.ts', '>=', strtotime($date))
            ->where('appa.ts', '<=', strtotime($date . ' 23:59:59'))
            ->leftJoin("app_{$code} as app", 'app.android_id', '=', 'appa.device_id')
            ->groupBy('app.version')->get()->all();

        $ks = Db::connection($this->dbConnection)->table("app_{$code}_ks as appk")
            ->select([
                'app.version',
                Db::raw("COUNT(*) as ks_total"),
                Db::raw("COUNT(IF(appk.oaid='',null,1)) as ks_param"),
            ])
            ->where('appk.ts', '>=', strtotime($date))
            ->where('appk.ts', '<=', strtotime($date . ' 23:59:59'))
            ->leftJoin("app_{$code} as app", 'app.android_id', '=', 'appk.device_id')
            ->groupBy('app.version')->get()->all();

        $result = [];
        $versions = $this->getVersions($list, $black, $jl, $ks);
        foreach ($versions as $version){
            $versionList = collect($list)->where('version', $version)->first();
            $blackList = collect($black)->where('version', $version)->first();
            $jlList = collect($jl)->where('version', $version)->first();
            $ksList = collect($ks)->where('version', $version)->first();
            $result[$version] = [
                'date' => $date,
                'app_code' => $code,
                'version' => $version,

                'install' => data_get($versionList, 'install', 0),
                'install_new' => data_get($versionList, 'install_new', 0),
                'active' => data_get($versionList, 'active', 0),
                'active_new' => data_get($versionList, 'active_new', 0),
                'guiyin' => data_get($versionList, 'guiyin', 0),
                'guiyin_new' => data_get($versionList, 'guiyin_new', 0),
                'install_param' => data_get($versionList, 'install_param', 0),
                'black' => data_get($blackList, 'black', 0),
                'client' => data_get($blackList, 'client', 0),
                'jl_total' => data_get($jlList, 'jl_total', 0),
                'jl_param' => data_get($jlList, 'jl_param', 0),
                'ks_total' => data_get($ksList, 'ks_total', 0),
                'ks_param' => data_get($ksList, 'ks_param', 0),
            ];
        }
        Db::transaction(function () use ($code, $date, $result) {
            Db::table('report_guiyin')->where('app_code', $code)->where('date', $date)->delete();
            Db::table('report_guiyin')->insert($result);
        });
    }

    public function getVersions(...$params)
    {
        $versions = [];
        foreach ($params as $v){
            $versions = collect($v)->pluck('version')->merge($versions)->toArray();
        }
        return collect($versions)->filter()->unique()->values()->toArray();
    }
}
