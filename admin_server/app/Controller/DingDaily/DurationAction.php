<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/17
 */

namespace App\Controller\DingDaily;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\App as AppUc;
use App\Model\AppDailyDuration;
use App\Utils\ValidateTrait;
use Hyperf\DbConnection\Db;
use Hyperf\View\RenderInterface;


class DurationAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'secret' => ['required'],//密钥
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        if ($params['secret'] != AppConst::DAILY_SECRET) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '参数错误');
        }

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $params['app_code'])->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $end_date = date('Y-m-d', strtotime('-1 day'));
        $start_date = date('Y-m-d', strtotime('-15 day'));

        $data_list = $this->setDefData($start_date, $end_date);

        $_query_list = AppDailyDuration::query()
            ->where('app_code', $params['app_code'])
            ->where('is_new_app_version', 1)
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy(['date', 'app_version'])
            ->orderBy('app_version', 'asc')
            ->select([
                'date',
                'app_version',
                Db::raw('SUM(num) AS total_num'),

                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'lt8\',`num`,0)) AS huawei_lt8_num'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'lt8\',`duration`,0)) AS huawei_lt8_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'ge8_lt11\',`num`,0)) AS huawei_ge8_lt11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'ge8_lt11\',`duration`,0)) AS huawei_ge8_lt11_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'ge11\',`num`,0)) AS huawei_ge11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\' and os_version_type=\'ge11\',`duration`,0)) AS huawei_ge11_duration'),

                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'lt8\',`num`,0)) AS xiaomi_lt8_num'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'lt8\',`duration`,0)) AS xiaomi_lt8_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'ge8_lt11\',`num`,0)) AS xiaomi_ge8_lt11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'ge8_lt11\',`duration`,0)) AS xiaomi_ge8_lt11_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'ge11\',`num`,0)) AS xiaomi_ge11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\' and os_version_type=\'ge11\',`duration`,0)) AS xiaomi_ge11_duration'),

                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'lt8\',`num`,0)) AS oppo_lt8_num'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'lt8\',`duration`,0)) AS oppo_lt8_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'ge8_lt11\',`num`,0)) AS oppo_ge8_lt11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'ge8_lt11\',`duration`,0)) AS oppo_ge8_lt11_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'ge11\',`num`,0)) AS oppo_ge11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\' and os_version_type=\'ge11\',`duration`,0)) AS oppo_ge11_duration'),

                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'lt8\',`num`,0)) AS vivo_lt8_num'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'lt8\',`duration`,0)) AS vivo_lt8_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'ge8_lt11\',`num`,0)) AS vivo_ge8_lt11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'ge8_lt11\',`duration`,0)) AS vivo_ge8_lt11_duration'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'ge11\',`num`,0)) AS vivo_ge11_num'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\' and os_version_type=\'ge11\',`duration`,0)) AS vivo_ge11_duration'),

            ])->get();

        $query_list = [];
        foreach ($_query_list as $row) {
            $row = collect($row)->toArray();
            $query_list[$row['date']] = $row;
        }

        foreach ($data_list as &$row) {
            if (isset($query_list[$row['date']])) {
                $row = array_merge($row, $query_list[$row['date']]);
            }

            $row['huawei_lt8_rate'] = $row['huawei_lt8_num'] ? round($row['huawei_lt8_duration'] / $row['huawei_lt8_num'], 2) : '/';
            $row['huawei_ge8_lt11_rate'] = $row['huawei_ge8_lt11_num'] ? round($row['huawei_ge8_lt11_duration'] / $row['huawei_ge8_lt11_num'], 2) : '/';
            $row['huawei_ge11_rate'] = $row['huawei_ge11_num'] ? round($row['huawei_ge11_duration'] / $row['huawei_ge11_num'], 2) : '/';

            $row['xiaomi_lt8_rate'] = $row['xiaomi_lt8_num'] ? round($row['xiaomi_lt8_duration'] / $row['xiaomi_lt8_num'], 2) : '/';
            $row['xiaomi_ge8_lt11_rate'] = $row['xiaomi_ge8_lt11_num'] ? round($row['xiaomi_ge8_lt11_duration'] / $row['xiaomi_ge8_lt11_num'], 2) : '/';
            $row['xiaomi_ge11_rate'] = $row['xiaomi_ge11_num'] ? round($row['xiaomi_ge11_duration'] / $row['xiaomi_ge11_num'], 2) : '/';

            $row['oppo_lt8_rate'] = $row['oppo_lt8_num'] ? round($row['oppo_lt8_duration'] / $row['oppo_lt8_num'], 2) : '/';
            $row['oppo_ge8_lt11_rate'] = $row['oppo_ge8_lt11_num'] ? round($row['oppo_ge8_lt11_duration'] / $row['oppo_ge8_lt11_num'], 2) : '/';
            $row['oppo_ge11_rate'] = $row['oppo_ge11_num'] ? round($row['oppo_ge11_duration'] / $row['oppo_ge11_num'], 2) : '/';

            $row['vivo_lt8_rate'] = $row['vivo_lt8_num'] ? round($row['vivo_lt8_duration'] / $row['vivo_lt8_num'], 2) : '/';
            $row['vivo_ge8_lt11_rate'] = $row['vivo_ge8_lt11_num'] ? round($row['vivo_ge8_lt11_duration'] / $row['vivo_ge8_lt11_num'], 2) : '/';
            $row['vivo_ge11_rate'] = $row['vivo_ge11_num'] ? round($row['vivo_ge11_duration'] / $row['vivo_ge11_num'], 2) : '/';
        }

        $data = [];
        $data['app_name'] = $App->app_name;
        $data['list'] = $data_list;

        $Render = di(RenderInterface::class);
        return $Render->render('ding_daily/duration', ['data' => $data]);
    }


    public function setDefData($start_date, $end_date): array
    {
        $default_data = [
            'app_version' => '/',

            'huawei_lt8_num' => 0,
            'huawei_lt8_duration' => 0,
            'huawei_ge8_lt11_num' => 0,
            'huawei_ge8_lt11_duration' => 0,
            'huawei_ge11_num' => 0,
            'huawei_ge11_duration' => 0,

            'xiaomi_lt8_num' => 0,
            'xiaomi_lt8_duration' => 0,
            'xiaomi_ge8_lt11_num' => 0,
            'xiaomi_ge8_lt11_duration' => 0,
            'xiaomi_ge11_num' => 0,
            'xiaomi_ge11_duration' => 0,

            'oppo_lt8_num' => 0,
            'oppo_lt8_duration' => 0,
            'oppo_ge8_lt11_num' => 0,
            'oppo_ge8_lt11_duration' => 0,
            'oppo_ge11_num' => 0,
            'oppo_ge11_duration' => 0,

            'vivo_lt8_num' => 0,
            'vivo_lt8_duration' => 0,
            'vivo_ge8_lt11_num' => 0,
            'vivo_ge8_lt11_duration' => 0,
            'vivo_ge11_num' => 0,
            'vivo_ge11_duration' => 0,
        ];
        $start_time = strtotime($start_date);//2022-06-30
        $end_time = strtotime($end_date);//2022-07-14
        $total = ($end_time - $start_time) / 86400 + 1;
        $data_list = [];
        for ($i = 0; $i < $total; $i++) {
            $default_data['date'] = date('Y-m-d', $start_time + ($i * 86400));
            $data_list[] = $default_data;
        }

        return $data_list;
    }
}