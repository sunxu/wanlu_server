<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\PlanRule;

use App\Constants\ErrorCode;
use App\Controller\AbstractAction;
use App\Exception\ValidationException;
use App\Model\App as AppUc;
use App\Model\Mkt\MktPlanRule;
use App\Utils\ValidateTrait;

class ListAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['required'],
            'search_id' => ['nullable'],
            'search_name' => ['nullable'],
            'status' => ['nullable'],
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

        /** @var AppUc $App */
        $App = AppUc::query()->where('app_code', $params['app_code'])->first();
        if (!$App) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, '不存在或未配置此应用');
        }

        $query = MktPlanRule::with(['advertiser'])
            ->where('app_code', $params['app_code'])
            ->where('deleted_time', 0);

        $query->when(isset($params['search_name']), function ($query) use ($params) {
            $query->where('name', 'like', "%{$params['search_name']}%");
        });

        $query->when(isset($params['search_id']), function ($query) use ($params) {
            $query->where('advertiser_id', $params['search_id']);
        });

        $query->orderBy('rule_id', 'desc');
        $paginate = $query->paginate($params['page_size']);
        $_list = $paginate->items();

        $list = [];
        foreach ($_list as $item) {
            $item = collect($item)->toArray();
            $item['advertiser_name'] = $item['advertiser']['advertiser_name'];
            $item['updated_time'] = date('Y-m-d H:i:s', $item['updated_time']);
            $list[] = $item;
        }

        return $this->responsePaginator($list, $paginate);
    }
}