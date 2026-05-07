<?php
declare(strict_types=1);
/**
 * Author: jyk-wsy
 * Date: 2022/6/14
 */

namespace App\Controller\Put\PlanRule;

use App\Controller\AbstractAction;
use App\Utils\ValidateTrait;

class BindAction extends AbstractAction
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

    }
}