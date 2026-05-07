<?php

declare(strict_types=1);

namespace App\Service\Task;

use Hyperf\DbConnection\Db;

class TaskService
{
    protected $dbConnection = 'tools';

    /**
     * 原始广告打点
     * @param string $appCode
     * @param string $start
     * @param string $end
     * @return array
     */
    public function ad(string $appCode, string $start, string $end)
    {
        $list = Db::connection($this->dbConnection)->SELECT("
            SELECT
                date,
                version,
                page,
                SUM( _request ) AS request_num,
                SUM( _full ) AS full_num,
                round( SUM( _full ) * 100 / SUM( _request ), 2 ) AS full_rate,
                SUM( _show ) AS show_num,
                round( SUM( _show ) * 100 / SUM( _full ), 2 ) AS show_rate
            FROM
            (   
                (
                    SELECT
                        date,
                        version,
                        page,
                        SUM( times ) AS _request,
                        0 AS _full,
                        0 AS _show
                    FROM
                        (SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_request WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                        UNION ALL
                        SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_request_temp WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                        )a
                    GROUP BY
                        date,
                        version,
                        page
                ) 
                UNION
                (
                    SELECT
                        date,
                        version,
                        page,
                        0 AS _request,
                        SUM( times ) AS _full,
                        0 AS _show
                    FROM
                        (
                        SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_full WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                        UNION ALL
                        SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_full_temp WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                        )b
                    GROUP BY
                        date,
                        version,
                        page
                ) 
                UNION
                (
                    SELECT
                        date,
                        version,
                        page,
                        0 AS _request,
                        0 AS _full,
                        SUM( times ) AS _show
                    FROM
                    (
                        SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_show WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                            UNION ALL
                        SELECT 
                            FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                            version,
                            page,
                            SUM( times ) AS times
                        FROM app_{$appCode}_event_ad_show_temp WHERE create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                        GROUP BY
                            date,
                            version,
                            page
                    )c
                    GROUP BY
                        date,
                        version,
                        page
                    )
            ) aa
            GROUP BY
                date,
                version,
                page
        ");

        return [
            'header' => [
                'date' => '日期', 'version' => '版本', 'page' => '页面', 'request_num' => '请求', 'full_num' => '填充', 'full_rate' => '填充率(%)', 'show_num' => '展现', 'show_rate' => '展现率(%)',
            ],
            'lists' => $list
        ];
    }

    /**
     * 原始外弹打点
     * @param string $appCode
     * @param string $start
     * @param string $end
     * @return array
     */
    public function dialog(string $appCode, string $start, string $end)
    {
        $list = Db::connection($this->dbConnection)->SELECT("
            SELECT
                date,
                version,
                key_type AS scene,
                req_type AS type,
                os_version AS os,
                SUM(count) AS count
            FROM
                (
                    SELECT 
                        FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                        version,
                        key_type,
                        req_type,
                        os_version,
                        COUNT(id) AS count 
                    FROM 
                        app_{$appCode}_event_dialog
                    WHERE
                        create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                    GROUP BY
                        date,
                        version,
                        os_version,
                        key_type,
                        req_type
                UNION ALL 
                    SELECT 
                        FROM_UNIXTIME( create_time, '%Y-%m-%d' ) AS date,
                        version,
                        key_type,
                        req_type,
                        os_version,
                        COUNT(id) AS count 
                    FROM 
                        app_{$appCode}_event_dialog_temp 
                    WHERE
                        create_time BETWEEN UNIX_TIMESTAMP( '{$start}' ) AND UNIX_TIMESTAMP( '{$end}' )
                    GROUP BY
                        date,
                        version,
                        os_version,
                        key_type,
                        req_type
                ) a
            GROUP BY
                date,
                version,
                os_version,
                key_type,
                req_type
        ");

        return [
            'header' => [
                'date' => '日期', 'version' => '版本', 'scene' => '场景', 'type' => '类型', 'os' => '系统', 'count' => '数量'
            ],
            'lists' => $list
        ];
    }

    /**
     * 策略组广告人均
     * @param string $appCode
     * @param string $start
     * @param string $end
     * @return array
     */
    public function strategy(string $appCode, string $start, string $end)
    {
        function tableTemp($beginTime)
        {
            return $beginTime < strtotime(date('Y-m-d 00:00:00', time() - 60 * 60 * 24)) ? '_temp' : '';
        }

        function getInstall($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            return Db::connection('tools')
                ->table("app_{$appCode}")
                ->whereBetween('create_time', [$startTimestamp, $endTimestamp])
                ->select(Db::raw("COUNT(*) as n"), Db::raw("(create_time%{$idNum}) as g"))
                ->groupBy('g')
                ->pluck('n', 'g')
                ->toArray();
        }

        function getInstallShow($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            $temp = tableTemp($startTimestamp);
            return Db::connection('tools')->table("app_{$appCode}_event_ad_show{$temp} as show")
                ->whereBetween('show.create_time', [$startTimestamp, $endTimestamp])
                ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
                ->whereBetween('app.create_time', [$startTimestamp, $endTimestamp])
                ->select(Db::raw("SUM(show.times) as n"), Db::raw("app.create_time % {$idNum} as g"))
                ->groupBy('g')
                ->pluck('n', 'g')
                ->toArray();
        }

        function getInstallRevenue($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            $temp = tableTemp($startTimestamp);
//            if (in_array($appCode, config('ad.test_app', []))) {
                return Db::connection('tools')->table("app_{$appCode}_event_ad_revenue{$temp} as eig")
                    ->where('eig.time', '>=', date('YmdHis', $startTimestamp))
                    ->where('eig.time', '<=', date('YmdHis', $endTimestamp))
                    ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
                    ->whereBetween('app.create_time', [$startTimestamp, $endTimestamp])
                    ->select(Db::raw("SUM(eig.amount) as n"), Db::raw("app.create_time % {$idNum} as g"))
                    ->groupBy('g')
                    ->pluck('n', 'g')
                    ->toArray();
//            } else {
//                return Db::connection('tools')->table("app_{$appCode}_event_interval_group{$temp} as eig")
//                    ->whereBetween('eig.start_time', [$startTimestamp, $endTimestamp])
//                    ->whereBetween('eig.end_time', [$startTimestamp, $endTimestamp])
//                    ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
//                    ->whereBetween('app.create_time', [$startTimestamp, $endTimestamp])
//                    ->select(Db::raw("SUM(eig.amount) as n"), Db::raw("app.create_time % {$idNum} as g"))
//                    ->groupBy('g')
//                    ->pluck('n', 'g')
//                    ->toArray();
//            }
        }

        function getDeu($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            return Db::connection('tools')->table("app_{$appCode}_active_keep as keep")
                ->where('keep.active_day', date("Ymd", $startTimestamp))
                ->where('keep.is_ad_report', 1)
                ->leftJoin("app_{$appCode} as app", "app.android_id", "keep.device_id")
                ->select(Db::raw("COUNT(DISTINCT keep.device_id) as n"), Db::raw("app.create_time % {$idNum} as g"))
                ->groupBy('g')
                ->pluck('n', 'g')
                ->toArray();
        }

        function getDeushow($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            $temp = tableTemp($startTimestamp);

            return Db::connection('tools')->table("app_{$appCode}_event_ad_show{$temp} as show")
                ->whereBetween('show.create_time', [$startTimestamp, $endTimestamp])
                ->leftJoin("app_{$appCode} as app", "app.android_id", "show.android_id")
                ->select(Db::raw("SUM(show.times) as n"), Db::raw("app.create_time % {$idNum} as g"))
                ->groupBy('g')
                ->pluck('n', 'g')
                ->toArray();
        }

        function getDeuRevenue($appCode, $startTimestamp, $endTimestamp, $idNum)
        {
            $temp = tableTemp($startTimestamp);
//            if (in_array($appCode, config('ad.test_app', []))) {
                return Db::connection('tools')->table("app_{$appCode}_event_ad_revenue{$temp} as eig")
                    ->where('eig.time', '>=', date('YmdHis', $startTimestamp))
                    ->where('eig.time', '<=', date('YmdHis', $endTimestamp))
                    ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
                    ->select(Db::raw("SUM(eig.amount) as n"), Db::raw("app.create_time % {$idNum} as g"))
                    ->groupBy('g')
                    ->pluck('n', 'g')
                    ->toArray();
//            } else {
//                return Db::connection('tools')->table("app_{$appCode}_event_interval_group{$temp} as eig")
//                    ->whereBetween('eig.start_time', [$startTimestamp, $endTimestamp])
//                    ->whereBetween('eig.end_time', [$startTimestamp, $endTimestamp])
//                    ->leftJoin("app_{$appCode} as app", "app.android_id", "eig.device_id")
//                    ->select(Db::raw("SUM(eig.amount) as n"), Db::raw("app.create_time % {$idNum} as g"))
//                    ->groupBy('g')
//                    ->pluck('n', 'g')
//                    ->toArray();
//            }
        }

        $ids = $list = Db::table('ad_strategy')
            ->where('app_code', $appCode)
            ->where('key', 'like', "%common%")
            ->where('deleted_time', 0)
            ->where('status', 1)
            ->pluck('id')
            ->toArray();

        $idNum = count($ids);
        if ($idNum == 0) {
            return [
                'header' => [
                    'date' => '日期', 'strategy' => '策略ID', 'install' => '安装', 'install_show_avg' => '安装人均', 'install_ecpm' => '安装 ECPM', 'deu' => 'DEU', 'deu_show_avg' => 'DEU 人均', 'deu_ecpm' => 'DEU ECPM'
                ],
                'lists' => []
            ];
        }

        $startTimestamp = strtotime($start);
        $endTimestamp = strtotime($end) - 1;

        $install = getInstall($appCode, $startTimestamp, $endTimestamp, $idNum);
        $installShow = getInstallShow($appCode, $startTimestamp, $endTimestamp, $idNum);
        $installRevenue = getInstallRevenue($appCode, $startTimestamp, $endTimestamp, $idNum);

        $deu = getDeu($appCode, $startTimestamp, $endTimestamp, $idNum);
        $deuShow = getDeushow($appCode, $startTimestamp, $endTimestamp, $idNum);
        $deuRevenue = getDeuRevenue($appCode, $startTimestamp, $endTimestamp, $idNum);

        $list = [];

        foreach ($ids as $index => $id) {
            $_install = $install[$index] ?? 0;
            $_installShow = $installShow[$index] ?? 0;
            $_installRevenue = $installRevenue[$index] ?? 0;

            $_deu = $deu[$index] ?? 0;
            $_deuShow = $deuShow[$index] ?? 0;
            $_deuRevenue = $deuRevenue[$index] ?? 0;

            $list[] = [
                'date' => date('Y-m-d', $startTimestamp),
                'strategy' => $id,
                'install' => $_install,
                'install_show_avg' => round($_install > 0 ? $_installShow / $_install : 0, 2),
                'install_ecpm' => round(($_installShow > 0 ? $_installRevenue / $_installShow : 0) * 1000, 2),
                'deu' => $_deu,
                'deu_show_avg' => round($_deu > 0 ? $_deuShow / $_deu : 0, 2),
                'deu_ecpm' => round(($_deuShow > 0 ? $_deuRevenue / $_deuShow : 0) * 1000, 2),
            ];
        }

        return [
            'header' => [
                'date' => '日期', 'strategy' => '策略ID', 'install' => '安装', 'install_show_avg' => '安装人均', 'install_ecpm' => '安装 ECPM', 'deu' => 'DEU', 'deu_show_avg' => 'DEU 人均', 'deu_ecpm' => 'DEU ECPM'
            ],
            'lists' => $list
        ];
    }
}
