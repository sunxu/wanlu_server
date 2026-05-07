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

class KeepAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'date_picker' => ['required', 'array'],//日期范围
            'date_picker.0' => ['required'],//开始日期
            'date_picker.1' => ['required'],//结束日期
            'type' => ['required'],//数据类型
            'app_code' => ['required'],//app_code
            'app_version' => ['nullable'],//
            'channel' => ['nullable'],//
            'launch_type' => ['nullable'],//
            'os_version' => ['nullable'],//
            'source' => ['nullable'],//
            'vendor' => ['nullable'],//
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
        $app_code = $params['app_code'];

        $default_list = $this->getDays($start, $end);
        if (count($default_list) > 30) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '最多查询30天的数据');
        }
        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $app_code)->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }
        switch ($App->type) {
            case AppConst::APP_TYPE_MIS:
                $query_list = $this->gain($app_code);
                break;
            case AppConst::APP_TYPE_TOOLS:
                $query_list = $this->tools();
                break;
            default:
                throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }
        foreach ($query_list as &$query_row) {
            $query_row = collect($query_row)->toArray();
            $query_row['day'] = $this->dateInt2Str($query_row['day']);
            $query_row['keep_num1'] = $query_row['active_num'] ? $query_row['keep_num1'] : 0;
            $query_row['keep_rate1'] = $query_row['active_num'] ? round($query_row['keep_num1'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num2'] = $query_row['active_num'] ? $query_row['keep_num2'] : 0;
            $query_row['keep_rate2'] = $query_row['active_num'] ? round($query_row['keep_num2'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num3'] = $query_row['active_num'] ? $query_row['keep_num3'] : 0;
            $query_row['keep_rate3'] = $query_row['active_num'] ? round($query_row['keep_num3'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num4'] = $query_row['active_num'] ? $query_row['keep_num4'] : 0;
            $query_row['keep_rate4'] = $query_row['active_num'] ? round($query_row['keep_num4'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num5'] = $query_row['active_num'] ? $query_row['keep_num5'] : 0;
            $query_row['keep_rate5'] = $query_row['active_num'] ? round($query_row['keep_num5'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num6'] = $query_row['active_num'] ? $query_row['keep_num6'] : 0;
            $query_row['keep_rate6'] = $query_row['active_num'] ? round($query_row['keep_num6'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num7'] = $query_row['active_num'] ? $query_row['keep_num7'] : 0;
            $query_row['keep_rate7'] = $query_row['active_num'] ? round($query_row['keep_num7'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num14'] = $query_row['active_num'] ? $query_row['keep_num14'] : 0;
            $query_row['keep_rate14'] = $query_row['active_num'] ? round($query_row['keep_num14'] / $query_row['active_num'], 4) * 100 . '%' : '--';

            $query_row['keep_num30'] = $query_row['active_num'] ? $query_row['keep_num30'] : 0;
            $query_row['keep_rate30'] = $query_row['active_num'] ? round($query_row['keep_num30'] / $query_row['active_num'], 4) * 100 . '%' : '--';

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

    public function getSelect(): array
    {
        $type = $this->request->input('type');
        switch ($type) {
            case 'install':
                $field_day = 'a.install_day';
                $keep_num_field = 'a.install_keep_num';
                break;
            case 'activate':
                $field_day = 'a.activate_day';
                $keep_num_field = 'a.activate_keep_num';
                break;
            default:
                throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在的类型');
        }
        $select = "{$field_day} as day, sum(if( {$keep_num_field} = 0, 1, 0)) as active_num, sum(if( {$keep_num_field} = " .
            "1, 1, 0)) as keep_num1, sum(if( {$keep_num_field} = 2, 1, 0)) as keep_num2, sum(if( {$keep_num_field} = 3" .
            ", 1, 0)) as keep_num3, sum(if( {$keep_num_field} = 4, 1, 0)) as keep_num4, sum(if( {$keep_num_field} = 5," .
            " 1, 0)) as keep_num5, sum(if( {$keep_num_field} = 6, 1, 0)) as keep_num6, sum(if( {$keep_num_field} = 7, " .
            "1, 0)) as keep_num7, sum(if( {$keep_num_field} = 14, 1, 0)) as keep_num14, sum(if( {$keep_num_field} = 30, 1, 0)) as keep_num30";
        return [$field_day, $select];
    }

    public function gain()
    {
        $appCode = $this->request->input('app_code');
        list($start, $end) = $this->request->input('date_picker');
        $start_int = $this->dateStr2Int($start);
        $end_int = $this->dateStr2Int($end);
        $params = $this->request->all();

        $AppActiveKeepTable = (new \App\ModelMis\AppActiveKeep())->setSuffix($appCode)->getTable();
        $UserAccountTable = (new \App\ModelMis\UserAccount())->setSuffix($appCode)->getTable();
        $query = Db::connection('mis')->table($AppActiveKeepTable . ' as a');
        if (!is_null(data_get($params, 'launch_type')) || !is_null(data_get($params, 'source'))) {
            $query->rightJoin($UserAccountTable . ' as b', 'a.user_id', '=', 'b.user_id');
        }
        list($field_day, $select) = $this->getSelect();
        $query->selectRaw($select);
        $query->whereBetween($field_day, [$start_int, $end_int]);
        /*版本号筛选*/
        $query->when(!is_null($appVersion = data_get($params, 'app_version')), function ($query) use ($appVersion) {
            $query->where('a.app_version', $appVersion);
        });
        /*渠道筛选*/
        $query->when(!is_null($channel = data_get($params, 'channel')), function ($query) use ($channel) {
            $query->where('a.channel_id', $channel);
        });
        /*投放方式筛选*/
        $query->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
            $query->where('b.launch_type', $launchType);
        });
        /*投放平台筛选*/
        $query->when(!is_null($source = data_get($params, 'source')), function ($query) use ($source) {
            $query->where('b.source', $source);
        });
        /*厂商筛选*/
        $query->when(!is_null($vendor = data_get($params, 'vendor')), function ($query) use ($vendor) {
            switch ($vendor) {
                case 'huawei':
                    $query->whereIn('a.vendor', ['huawei', 'honor']);
                    break;
                case 'xiaomi':
                    $query->whereIn('a.vendor', ['xiaomi', 'redmi']);
                    break;
                case 'oppo':
                    $query->whereIn('a.vendor', ['oppo', 'realme']);
                    break;
                case 'vivo':
                    $query->where('a.vendor', 'vivo');
                    break;
                case 'other':
                    $query->whereNotIn('a.vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                    break;
            }
        });
        /*系统版本筛选*/
        $query->when(!is_null($osVersion = data_get($params, 'os_version')), function ($query) use ($osVersion) {
            $where = '';
            switch ($osVersion) {
                case 'lt-8': //Android 8以下
                    $where = 'CAST(a.os_version as SIGNED) < 8';
                    break;
                case '8-10'://Android 8-10
                    $where = '(CAST(a.os_version as SIGNED) >= 8 and CAST(a.os_version as SIGNED) <= 10)';
                    break;
                case 'gt-10'://Android 10以上
                    $where = 'CAST(a.os_version as SIGNED) > 10';
            }
            return $query->whereRaw($where);
        });

        $query->groupBy([$field_day]);
        $query->orderBy($field_day, 'desc');
        return $query->get()->toArray();
    }

    public function tools()
    {
        $appCode = $this->request->input('app_code');
        list($start, $end) = $this->request->input('date_picker');
        $start_int = $this->dateStr2Int($start);
        $end_int = $this->dateStr2Int($end);
        $params = $this->request->all();

        $AppActiveKeepTable = (new \App\ModelTools\AppActiveKeep())->setSuffix($appCode)->getTable();
        $UserAccountTable = (new \App\ModelTools\Device())->setSuffix($appCode)->getTable();

        $query = Db::connection('tools')->table($AppActiveKeepTable . ' as a');
        if (
            !is_null(data_get($params, 'app_version')) ||
            !is_null(data_get($params, 'channel')) ||
            !is_null(data_get($params, 'launch_type')) ||
            !is_null(data_get($params, 'source')) ||
            !is_null(data_get($params, 'vendor')) ||
            !is_null(data_get($params, 'os_version'))
        ) {
            $query->rightJoin($UserAccountTable . ' as b', 'a.device_id', '=', 'b.android_id');
        }

        list($field_day, $select) = $this->getSelect();
        $query->selectRaw($select);
        $query->whereBetween($field_day, [$start_int, $end_int]);
        /*版本号筛选*/
        $query->when($appVersion = data_get($params, 'app_version'), function ($query) use ($appVersion) {
            $query->where('b.version', $appVersion);
        });
        /*渠道筛选*/
        $query->when($channel = data_get($params, 'channel'), function ($query) use ($channel) {
            $query->where('b.channel', $channel);
        });
        /*投放方式筛选*/
        $query->when(!is_null($launchType = data_get($params, 'launch_type')), function ($query) use ($launchType) {
            $advertiserIds = MktAdvertiser::query()->where('launch_type', $launchType)->select('advertiser_id')->pluck('advertiser_id')->toArray();
            $query->whereIn('b.account_id', $advertiserIds);
        });
        /*投放平台筛选*/
        $query->when($source = MktConst::PLATFORM_SOURCE[data_get($params, 'source')] ?? '', function ($query) use ($source) {
            $query->where('b.source', $source);
        });
        /*厂商筛选*/
        $query->when($vendor = data_get($params, 'vendor'), function ($query) use ($vendor) {
            switch ($vendor) {
                case 'huawei':
                    $query->whereIn('b.vendor', ['huawei', 'honor']);
                    break;
                case 'xiaomi':
                    $query->whereIn('b.vendor', ['xiaomi', 'redmi']);
                    break;
                case 'oppo':
                    $query->whereIn('b.vendor', ['oppo', 'realme']);
                    break;
                case 'vivo':
                    $query->where('b.vendor', 'vivo');
                    break;
                case 'other':
                    $query->whereNotIn('b.vendor', ['huawei', 'honor', 'xiaomi', 'redmi', 'oppo', 'realme', 'vivo']);
                    break;
            }
        });
        /*系统版本筛选*/
        $query->when($osVersion = data_get($params, 'os_version'), function ($query) use ($osVersion) {
            $where = '';
            switch ($osVersion) {
                case 'lt-8':
                    $where = 'CAST(b.os_version as SIGNED) < 8';
                    break;
                case '8':
                    $where = 'CAST(b.os_version as SIGNED) = 8';
                    break;
                case '9':
                    $where = 'CAST(b.os_version as SIGNED) = 9';
                    break;
                case '10':
                    $where = 'CAST(b.os_version as SIGNED) = 10';
                    break;
                case '11':
                    $where = 'CAST(b.os_version as SIGNED) = 11';
                    break;
                case '12':
                    $where = 'CAST(b.os_version as SIGNED) = 12';
                    break;
                case '13':
                    $where = 'CAST(b.os_version as SIGNED) = 13';
                    break;
                case '14':
                    $where = 'CAST(b.os_version as SIGNED) = 14';
                    break;
                case '15':
                    $where = 'CAST(b.os_version as SIGNED) = 15';
                    break;
                case '16':
                    $where = 'CAST(b.os_version as SIGNED) >= 16';
                    break;
            }
            $query->whereRaw($where);
        });

        $query->groupBy([$field_day]);
        $query->orderBy($field_day);
        return $query->get()->toArray();
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
            'active_num' => 0,
            'keep_num1' => 0,
            'keep_num2' => 0,
            'keep_num3' => 0,
            'keep_num4' => 0,
            'keep_num5' => 0,
            'keep_num6' => 0,
            'keep_num7' => 0,
            'keep_num14' => 0,
            'keep_num30' => 0,
            'keep_rate1' => '--',
            'keep_rate2' => '--',
            'keep_rate3' => '--',
            'keep_rate4' => '--',
            'keep_rate5' => '--',
            'keep_rate6' => '--',
            'keep_rate7' => '--',
            'keep_rate14' => '--',
            'keep_rate30' => '--',
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