<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\Advertiser;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Model\App as AppUc;
use App\Utils\ValidateTrait;

class ListAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],//app_code
            'is_bind_app' => ['required'],//是否关联应用
            'status' => ['nullable'],//状态
            'launch_type' => ['nullable'],//投放方式
            'search_id' => ['nullable'],//搜索ID
            'page' => ['nullable', 'integer'],
            'page_size' => ['nullable', 'integer'],
        ];
    }

    public function default(): array
    {
        return [
            'page' => 1,
            'page_size' => 10
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        $query = Advertiser::query();

        $app = AppUc::query()->pluck('app_name', 'app_code');
        //关联应用
        $query->where('app_code', $params['is_bind_app'] ? '=' : '!=', $params['app_code']);
        /*投放方式筛选*/
        $launch_type = data_get($params, 'launch_type');
        $query->when(isset($launch_type), function ($query) use ($launch_type) {
            $query->where('launch_type', $launch_type);
        });

        /*状态筛选*/
        $status = data_get($params, 'status');
        $query->when(isset($status), function ($query) use ($status) {
            $query->where('status', $status);
        });

        /*ID搜索筛选*/
        $search_id = data_get($params, 'search_id');
        $query->when(isset($search_id), function ($query) use ($search_id) {
            $query->where(function ($query) use ($search_id) {
                $query->where('advertiser_id', $search_id);
                $query->orWhere('advertiser_name', 'like', '%' . $search_id . '%');
            });
        });

        $query->orderBy('created_time', 'desc');
        $paginate = $query->paginate($params['page_size']);
        $_list = $paginate->items();
        $list = [];

        foreach ($_list as $item) {
            $item = collect($item)->toArray();
            $item['create_time'] = date('Y-m-d H:i:s', $item['created_time']);
            $item['update_time'] = date('Y-m-d H:i:s', $item['updated_time']);
            $item['app_name'] = data_get($app, $item['app_code'], '');
            $item['extra'] = $item['extra'] ? json_encode($item['extra'], 320) : null;
            $list[] = $item;
        }

        return $this->responsePaginator($list, $paginate);
    }
}