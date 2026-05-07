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
use App\Model\AppDailyShowKeep;
use App\Utils\ValidateTrait;
use Hyperf\DbConnection\Db;
use Hyperf\View\RenderInterface;


class AvgShowAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'launch_type' => ['required'],//投放方式
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

        $_query_list = AppDailyShowKeep::query()
            ->where('app_code', $params['app_code'])
            ->where('launch_type', $params['launch_type'])
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy(['date'])
            ->select([
                'date',
                Db::raw('SUM(`install`) as total_install'),
                Db::raw('SUM(`show`) as total_show'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\',`install`,0)) AS huawei_install'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\',`show`,0)) AS huawei_show'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\',`install`,0)) AS xiaomi_install'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\',`show`,0)) AS xiaomi_show'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\',`install`,0)) AS oppo_install'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\',`show`,0)) AS oppo_show'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\',`install`,0)) AS vivo_install'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\',`show`,0)) AS vivo_show')
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
            $row['total_avg_show'] = $row['total_install'] ? round($row['total_show'] / $row['total_install'], 2) : '/';
            $row['huawei_avg_show'] = $row['huawei_install'] ? round($row['huawei_show'] / $row['huawei_install'], 2) : '/';
            $row['xiaomi_avg_show'] = $row['xiaomi_install'] ? round($row['xiaomi_show'] / $row['xiaomi_install'], 2) : '/';
            $row['oppo_avg_show'] = $row['oppo_install'] ? round($row['oppo_show'] / $row['oppo_install'], 2) : '/';
            $row['vivo_avg_show'] = $row['vivo_install'] ? round($row['vivo_show'] / $row['vivo_install'], 2) : '/';

            $row['huawei_install_rate'] = $row['total_install'] ? round($row['huawei_install'] / $row['total_install'], 4) * 100 . '%' : '/';
            $row['xiaomi_install_rate'] = $row['total_install'] ? round($row['xiaomi_install'] / $row['total_install'], 4) * 100 . '%' : '/';
            $row['oppo_install_rate'] = $row['total_install'] ? round($row['oppo_install'] / $row['total_install'], 4) * 100 . '%' : '/';
            $row['vivo_install_rate'] = $row['total_install'] ? round($row['vivo_install'] / $row['total_install'], 4) * 100 . '%' : '/';
        }

        $data = [];
        $data['app_name'] = $App->app_name;
        $data['launch_type_name'] = AppConst::LAUNCH_TYPE_NAME_MAP[$params['launch_type']];
        $data['list'] = $data_list;

        $Render = di(RenderInterface::class);
        return $Render->render('ding_daily/avg_show', ['data' => $data]);
    }


    public function setDefData($start_date, $end_date): array
    {
        $default_data = [
            'total_install' => 0,
            'total_show' => 0,
            'huawei_install' => 0,
            'huawei_show' => 0,
            'xiaomi_install' => 0,
            'xiaomi_show' => 0,
            'oppo_install' => 0,
            'oppo_show' => 0,
            'vivo_install' => 0,
            'vivo_show' => 0,


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