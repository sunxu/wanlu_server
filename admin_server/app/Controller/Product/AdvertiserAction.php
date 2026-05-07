<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/9
 */

namespace App\Controller\Product;

use App\Controller\AbstractAction;
use App\Model\Mkt\MktAdvertiser as Advertiser;
use App\Utils\ValidateTrait;

class AdvertiserAction extends AbstractAction
{
    use ValidateTrait;

    public function rules(): array
    {
        return [
            'app_code' => ['nullable'],
        ];
    }

    public function handle()
    {
        $params = $this->validated($this->request->all());
        $app_code = data_get($params, 'app_code');
        $list = Advertiser::query()
            ->where('status', 1)
            ->when($app_code, function ($query, $app_code) {
                return $query->where('app_code', $app_code);
            })->select(['advertiser_id', 'advertiser_name', 'app_code'])
            ->orderBy('created_time', 'desc')
            ->get();

        $data['list'] = $list;
        return $this->responseSuccess($data);
    }
}