<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/9
 */

namespace App\Controller\Product;

use App\Constants\AppConst;
use App\Constants\ErrorCode;
use App\Constants\MktConst;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\App as AppUc;
use App\Model\Mkt\MktAdvertiser;
use App\Utils\ValidateTrait;
use Hyperf\DbConnection\Db;

class VendorAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'date_picker' => ['required'],//日期范围
            'type' => ['required'],//数据类型
            'app_code' => ['required'],//app_code
            'app_version' => ['nullable'],//
            'launch_type' => ['nullable'],//
            'source' => ['nullable'],//
            'advertiser_id' => ['nullable'],//
        ];
    }

    /**
     * 自定义属性
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'app_code' => '应用名称',
            'date_picker.0' => '开始日期',
            'date_picker.1' => '结束日期',
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());

        $start = $params['date_picker'][0];
        $end = $params['date_picker'][1];
        $start_int = $this->dateStr2Int($start);
        $end_int = $this->dateStr2Int($end);
        $type = $params['type'];
        $app_code = $params['app_code'];
        $app_version = data_get($params, 'app_version');
        $launch_type = data_get($params, 'launch_type');
        $source = data_get($params, 'source');
        $advertiser_id = data_get($params, 'advertiser_id');

        $default_list = $this->getDays($start, $end);

        if (count($default_list) > 30) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '最多查询30天的数据');
        }

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $app_code)->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $db_pool = data_get(AppConst::APP_DB_MAP, $App->type);
        if (!$db_pool) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $query_list = [];

        $field_map = [
            'install' => ['field_day' => 'a.install_day', 'where' => [['a.install_day', '>=', $start_int], ['a.install_day', '<=', $end_int], ['a.install_keep_num', '=', 0]]],
            'activate' => ['field_day' => 'a.activate_day', 'where' => [['a.activate_day', '>=', $start_int], ['a.activate_day', '<=', $end_int], ['a.activate_keep_num', '=', 0]]],
            'dau' => ['field_day' => 'a.active_day', 'where' => [['a.active_day', '>=', $start_int], ['a.active_day', '<=', $end_int]]],
            'deu' => ['field_day' => 'a.active_day', 'where' => [['a.active_day', '>=', $start_int], ['a.active_day', '<=', $end_int], ['a.is_ad_report', '=', 1]]],
        ];
        $field = $field_map[$type];
        $field_day = $field['field_day'];
        $where = $field['where'];


        /*网赚*/
        if ($App->type == AppConst::APP_TYPE_MIS) {
            $select = $field_day . ' as day, 
            count(*) as total_num,
            sum(if(a.vendor in (\'huawei\', \'honor\'), 1, 0)) as huawei_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) < 8, 1, 0)) as huawei_lt8_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) = 8, 1, 0)) as huawei_eq8_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) = 9 , 1, 0)) as huawei_eq9_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) = 10 , 1, 0)) as huawei_eq10_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) = 11 , 1, 0)) as huawei_ge11_num,
            sum(if(a.vendor in (\'huawei\', \'honor\') and CAST(a.os_version as SIGNED) >= 12 , 1, 0)) as huawei_ge12_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\'), 1, 0)) as xiaomi_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) < 8, 1, 0)) as xiaomi_lt8_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) = 8, 1, 0)) as xiaomi_eq8_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) = 9 , 1, 0)) as xiaomi_eq9_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) = 10 , 1, 0)) as xiaomi_eq10_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) = 11 , 1, 0)) as xiaomi_ge11_num,
            sum(if(a.vendor in (\'xiaomi\', \'redmi\') and CAST(a.os_version as SIGNED) >= 12 , 1, 0)) as xiaomi_ge12_num,
            sum(if(a.vendor in (\'oppo\', \'realme\'), 1, 0)) as oppo_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) < 8, 1, 0)) as oppo_lt8_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) = 8, 1, 0)) as oppo_eq8_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) = 9 , 1, 0)) as oppo_eq9_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) = 10 , 1, 0)) as oppo_eq10_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) = 11 , 1, 0)) as oppo_ge11_num,
            sum(if(a.vendor in (\'oppo\', \'realme\') and CAST(a.os_version as SIGNED) >= 12 , 1, 0)) as oppo_ge12_num,
            sum(if(a.vendor = \'vivo\', 1, 0)) as vivo_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) < 8, 1, 0)) as vivo_lt8_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) = 8, 1, 0)) as vivo_eq8_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) = 9 , 1, 0)) as vivo_eq9_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) = 10 , 1, 0)) as vivo_eq10_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) = 11 , 1, 0)) as vivo_ge11_num,
            sum(if(a.vendor = \'vivo\' and CAST(a.os_version as SIGNED) >= 12 , 1, 0)) as vivo_ge12_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\'), 1, 0)) as other_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) < 8, 1, 0)) as other_lt8_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) = 8, 1, 0)) as other_eq8_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) = 9 , 1, 0)) as other_eq9_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) = 10 , 1, 0)) as other_eq10_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) = 11 , 1, 0)) as other_ge11_num,
            sum(if(a.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(a.os_version as SIGNED) >= 12 , 1, 0)) as other_ge12_num';

            $AppActiveKeepTable = (new \App\ModelMis\AppActiveKeep())->setSuffix($app_code)->getTable();
            $UserAccountTable = (new \App\ModelMis\UserAccount())->setSuffix($app_code)->getTable();
            $query = Db::connection('mis')->table($AppActiveKeepTable . ' as a');
            if (
                isset($launch_type)
                || isset($source)
                || isset($advertiser_id)
            ) {
                $query->rightJoin($UserAccountTable . ' as b', 'a.user_id', '=', 'b.user_id');
            }

            $query->selectRaw($select);
            $query->where($where);
            /*版本号筛选*/
            $query->when(isset($app_version), function ($query) use ($app_version) {
                return $query->where('a.app_version', $app_version);
            });
            /*投放方式筛选*/
            $query->when(isset($launch_type), function ($query) use ($launch_type) {
                $query->where('b.launch_type', $launch_type);
            });
            /*投放平台筛选*/
            $query->when(isset($source), function ($query) use ($source) {
                $query->where('b.source', $source);
            });
            /*广告主筛选*/
            $query->when(isset($advertiser_id), function ($query) use ($advertiser_id) {
                $query->whereIn('b.advertiser_id', $advertiser_id);
            });

            $query->groupBy([$field_day]);
            $query->orderBy($field_day);
            $query_list = $query->get()->toArray();
        }

        /*工具*/
        if ($App->type == AppConst::APP_TYPE_TOOLS) {

            $select = $field_day . ' as day, 
            count(*) as total_num,
            sum(if(b.vendor in (\'huawei\', \'honor\'), 1, 0)) as huawei_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) < 8, 1, 0)) as huawei_lt8_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 8, 1, 0)) as huawei_eq8_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 9 , 1, 0)) as huawei_eq9_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 10 , 1, 0)) as huawei_eq10_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 11 , 1, 0)) as huawei_ge11_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 12 , 1, 0)) as huawei_ge12_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 13 , 1, 0)) as huawei_ge13_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 14 , 1, 0)) as huawei_ge14_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) = 15 , 1, 0)) as huawei_ge15_num,
            sum(if(b.vendor in (\'huawei\', \'honor\') and CAST(b.os_version as SIGNED) >= 16 , 1, 0)) as huawei_ge16_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\'), 1, 0)) as xiaomi_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) < 8, 1, 0)) as xiaomi_lt8_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 8, 1, 0)) as xiaomi_eq8_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 9 , 1, 0)) as xiaomi_eq9_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 10 , 1, 0)) as xiaomi_eq10_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 11 , 1, 0)) as xiaomi_ge11_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 12 , 1, 0)) as xiaomi_ge12_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 13 , 1, 0)) as xiaomi_ge13_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 14 , 1, 0)) as xiaomi_ge14_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) = 15 , 1, 0)) as xiaomi_ge15_num,
            sum(if(b.vendor in (\'xiaomi\', \'redmi\') and CAST(b.os_version as SIGNED) >= 16 , 1, 0)) as xiaomi_ge16_num,
            sum(if(b.vendor in (\'oppo\', \'realme\'), 1, 0)) as oppo_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) < 8, 1, 0)) as oppo_lt8_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 8, 1, 0)) as oppo_eq8_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 9 , 1, 0)) as oppo_eq9_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 10 , 1, 0)) as oppo_eq10_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 11 , 1, 0)) as oppo_ge11_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 12 , 1, 0)) as oppo_ge12_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 13 , 1, 0)) as oppo_ge13_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 14 , 1, 0)) as oppo_ge14_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) = 15 , 1, 0)) as oppo_ge15_num,
            sum(if(b.vendor in (\'oppo\', \'realme\') and CAST(b.os_version as SIGNED) >= 16 , 1, 0)) as oppo_ge16_num,
            sum(if(b.vendor = \'vivo\', 1, 0)) as vivo_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) < 8, 1, 0)) as vivo_lt8_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 8, 1, 0)) as vivo_eq8_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 9 , 1, 0)) as vivo_eq9_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 10 , 1, 0)) as vivo_eq10_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 11 , 1, 0)) as vivo_ge11_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 12 , 1, 0)) as vivo_ge12_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 13 , 1, 0)) as vivo_ge13_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 14 , 1, 0)) as vivo_ge14_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) = 15 , 1, 0)) as vivo_ge15_num,
            sum(if(b.vendor = \'vivo\' and CAST(b.os_version as SIGNED) >= 16 , 1, 0)) as vivo_ge16_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\'), 1, 0)) as other_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) < 8, 1, 0)) as other_lt8_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 8, 1, 0)) as other_eq8_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 9 , 1, 0)) as other_eq9_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 10 , 1, 0)) as other_eq10_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 11 , 1, 0)) as other_ge11_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 12 , 1, 0)) as other_ge12_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 13 , 1, 0)) as other_ge13_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 14 , 1, 0)) as other_ge14_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) = 15 , 1, 0)) as other_ge15_num,
            sum(if(b.vendor not in (\'huawei\',\'honor\',\'xiaomi\',\'redmi\',\'oppo\',\'realme\',\'vivo\') and CAST(b.os_version as SIGNED) >= 16 , 1, 0)) as other_ge16_num';

            $AppActiveKeepTable = (new \App\ModelTools\AppActiveKeep())->setSuffix($app_code)->getTable();
            $UserAccountTable = (new \App\ModelTools\Device())->setSuffix($app_code)->getTable();

            $query = Db::connection('tools')->table($AppActiveKeepTable . ' as a');
            $query->rightJoin($UserAccountTable . ' as b', 'a.device_id', '=', 'b.android_id');


            $query->selectRaw($select);
            $query->where($where);
            /*版本号筛选*/
            $query->when($app_version, function ($query) use ($app_version) {
                $query->where('b.version', $app_version);
            });
            /*投放方式筛选*/
            $query->when(!is_null($launch_type), function ($query) use ($launch_type) {
                $advertiserIds = MktAdvertiser::query()->where('launch_type', $launch_type)->select('advertiser_id')->pluck('advertiser_id')->toArray();
                $query->whereIn('b.account_id', $advertiserIds);
            });
            /*投放平台筛选*/
            $query->when($source = MktConst::PLATFORM_SOURCE[$source] ?? '', function ($query) use ($source) {
                $query->where('b.source', $source);
            });
            /*广告主筛选*/
            $query->when($advertiser_id, function ($query) use ($advertiser_id) {
                $query->whereIn('b.account_id', $advertiser_id);
            });

            $query->groupBy([$field_day]);
            $query->orderBy($field_day, 'desc');
            $query_list = $query->get()->toArray();
        }


        foreach ($query_list as &$query_row) {
            $query_row = collect($query_row)->toArray();
            $query_row['day'] = $this->dateInt2Str($query_row['day']);
            $query_row['huawei_rate'] = $query_row['total_num'] ? round($query_row['huawei_num'] / $query_row['total_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_lt8_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_lt8_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_eq8_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_eq8_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_eq9_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_eq9_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_eq10_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_eq10_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge11_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge11_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge12_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge12_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge13_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge13_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge14_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge14_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge15_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge15_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['huawei_ge16_rate'] = $query_row['huawei_num'] ? round($query_row['huawei_ge16_num'] / $query_row['huawei_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_rate'] = $query_row['total_num'] ? round($query_row['xiaomi_num'] / $query_row['total_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_lt8_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_lt8_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_eq8_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_eq8_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_eq9_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_eq9_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_eq10_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_eq10_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge11_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge11_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge12_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge12_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge13_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge13_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge14_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge14_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge15_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge15_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['xiaomi_ge16_rate'] = $query_row['xiaomi_num'] ? round($query_row['xiaomi_ge16_num'] / $query_row['xiaomi_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_rate'] = $query_row['total_num'] ? round($query_row['oppo_num'] / $query_row['total_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_lt8_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_lt8_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_eq8_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_eq8_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_eq9_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_eq9_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_eq10_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_eq10_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge11_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge11_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge12_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge12_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge13_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge13_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge14_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge14_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge15_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge15_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['oppo_ge16_rate'] = $query_row['oppo_num'] ? round($query_row['oppo_ge16_num'] / $query_row['oppo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_rate'] = $query_row['total_num'] ? round($query_row['vivo_num'] / $query_row['total_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_lt8_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_lt8_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_eq8_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_eq8_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_eq9_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_eq9_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_eq10_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_eq10_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge11_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge11_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge12_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge12_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge13_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge13_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge14_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge14_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge15_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge15_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['vivo_ge16_rate'] = $query_row['vivo_num'] ? round($query_row['vivo_ge16_num'] / $query_row['vivo_num'], 4) * 100 . '%' : '--';
            $query_row['other_rate'] = $query_row['total_num'] ? round($query_row['other_num'] / $query_row['total_num'], 4) * 100 . '%' : '--';
            $query_row['other_lt8_rate'] = $query_row['other_num'] ? round($query_row['other_lt8_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_eq8_rate'] = $query_row['other_num'] ? round($query_row['other_eq8_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_eq9_rate'] = $query_row['other_num'] ? round($query_row['other_eq9_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_eq10_rate'] = $query_row['other_num'] ? round($query_row['other_eq10_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge11_rate'] = $query_row['other_num'] ? round($query_row['other_ge11_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge12_rate'] = $query_row['other_num'] ? round($query_row['other_ge12_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge13_rate'] = $query_row['other_num'] ? round($query_row['other_ge13_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge14_rate'] = $query_row['other_num'] ? round($query_row['other_ge14_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge15_rate'] = $query_row['other_num'] ? round($query_row['other_ge15_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';
            $query_row['other_ge16_rate'] = $query_row['other_num'] ? round($query_row['other_ge16_num'] / $query_row['other_num'], 4) * 100 . '%' : '--';

            foreach ($default_list as $key => $value) {
                if ($value['day'] == $query_row['day']) {
                    $default_list[$key] = $query_row;
                }
            }
        }

        $data = [];
        $data['query_list'] = $query_list;
        $data['list'] = $default_list;

        return $this->responseSuccess($data);
    }


    public function dateInt2Str(int $day)
    {
        $time = strtotime((string)$day);
        return date('Y-m-d', $time);
    }

    public function dateStr2Int(string $day): int
    {
        $time = strtotime((string)$day);
        return (int)date('Ymd', $time);
    }

    public function getDays($start, $end): array
    {
        $default_data = [
            'total_num' => 0,
            'huawei_num' => 0,
            'huawei_rate' => '--',
            'huawei_lt8_num' => 0,
            'huawei_eq8_num' => 0,
            'huawei_eq9_num' => 0,
            'huawei_eq10_num' => 0,
            'huawei_ge11_num' => 0,
            'huawei_ge12_num' => 0,
            'huawei_ge13_num' => 0,
            'huawei_ge14_num' => 0,
            'huawei_ge15_num' => 0,
            'huawei_ge16_num' => 0,
            'xiaomi_num' => 0,
            'xiaomi_rate' => '--',
            'xiaomi_lt8_num' => 0,
            'xiaomi_eq8_num' => 0,
            'xiaomi_eq9_num' => 0,
            'xiaomi_eq10_num' => 0,
            'xiaomi_ge11_num' => 0,
            'xiaomi_ge12_num' => 0,
            'xiaomi_ge13_num' => 0,
            'xiaomi_ge14_num' => 0,
            'xiaomi_ge15_num' => 0,
            'xiaomi_ge16_num' => 0,
            'oppo_num' => 0,
            'oppo_rate' => '--',
            'oppo_lt8_num' => 0,
            'oppo_eq8_num' => 0,
            'oppo_eq9_num' => 0,
            'oppo_eq10_num' => 0,
            'oppo_ge11_num' => 0,
            'oppo_ge12_num' => 0,
            'oppo_ge13_num' => 0,
            'oppo_ge14_num' => 0,
            'oppo_ge15_num' => 0,
            'oppo_ge16_num' => 0,
            'vivo_num' => 0,
            'vivo_rate' => '--',
            'vivo_lt8_num' => 0,
            'vivo_eq8_num' => 0,
            'vivo_eq9_num' => 0,
            'vivo_eq10_num' => 0,
            'vivo_ge11_num' => 0,
            'vivo_ge12_num' => 0,
            'vivo_ge13_num' => 0,
            'vivo_ge14_num' => 0,
            'vivo_ge15_num' => 0,
            'vivo_ge16_num' => 0,
            'other_num' => 0,
            'other_rate' => '--',
            'other_lt8_num' => 0,
            'other_eq8_num' => 0,
            'other_eq9_num' => 0,
            'other_eq10_num' => 0,
            'other_ge11_num' => 0,
            'other_ge12_num' => 0,
            'other_ge13_num' => 0,
            'other_ge14_num' => 0,
            'other_ge15_num' => 0,
            'other_ge16_num' => 0,
        ];
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        $total = ($end_time - $start_time) / 86400 + 1;
        $data_list = [];
        for ($i = ($total - 1); $i >= 0; $i--) {
            $default_data['day'] = date('Y-m-d', $start_time + ($i * 86400));
            $data_list[] = $default_data;
        }

        return $data_list;
    }
}