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


class KeepAction extends AbstractAction
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

        $end_date = date('Y-m-d', strtotime('-2 day'));
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
                Db::raw('SUM(`keep`) as total_keep'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\',`install`,0)) AS huawei_install'),
                Db::raw('SUM(IF(`vendor_type`=\'huawei\',`keep`,0)) AS huawei_keep'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\',`install`,0)) AS xiaomi_install'),
                Db::raw('SUM(IF(`vendor_type`=\'xiaomi\',`keep`,0)) AS xiaomi_keep'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\',`install`,0)) AS oppo_install'),
                Db::raw('SUM(IF(`vendor_type`=\'oppo\',`keep`,0)) AS oppo_keep'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\',`install`,0)) AS vivo_install'),
                Db::raw('SUM(IF(`vendor_type`=\'vivo\',`keep`,0)) AS vivo_keep')
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

            $row['total_keep_rate'] = $row['total_install'] ? round($row['total_keep'] / $row['total_install'], 4) * 100 . '%' : '/';
            $row['huawei_keep_rate'] = $row['total_install'] ? round($row['huawei_keep'] / $row['huawei_install'], 4) * 100 . '%' : '/';
            $row['xiaomi_keep_rate'] = $row['total_install'] ? round($row['xiaomi_keep'] / $row['xiaomi_install'], 4) * 100 . '%' : '/';
            $row['oppo_keep_rate'] = $row['total_install'] ? round($row['oppo_keep'] / $row['oppo_install'], 4) * 100 . '%' : '/';
            $row['vivo_keep_rate'] = $row['total_install'] ? round($row['vivo_keep'] / $row['vivo_install'], 4) * 100 . '%' : '/';
        }

        $data = [];
        $data['app_name'] = $App->app_name;
        $data['launch_type_name'] = AppConst::LAUNCH_TYPE_NAME_MAP[$params['launch_type']];
        $data['list'] = $data_list;

        $Render = di(RenderInterface::class);
        return $Render->render('ding_daily/keep', ['data' => $data]);

    }




    public function setDefData($start_date, $end_date): array
    {
        $default_data = [
            'total_install' => 0,
            'total_keep' => 0,
            'huawei_install' => 0,
            'huawei_keep' => 0,
            'xiaomi_install' => 0,
            'xiaomi_keep' => 0,
            'oppo_install' => 0,
            'oppo_keep' => 0,
            'vivo_install' => 0,
            'vivo_keep' => 0,


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