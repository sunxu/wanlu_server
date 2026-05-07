<?php

declare(strict_types=1);
/**
 *
 * Author: jyk-wsy
 * Date: 2022/3/29
 */

namespace App\Utils;

use App\Constants\ErrorCode;
use App\Exception\ValidationException;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;

/**
 * @method array rules(array $inputs = []) 验证规则 $input为验证数据
 * @method array messages() 规则错误自定义
 * @method array default() 获取默认参数设置
 * @method array attributes() 规则属性替换
 * @method array validateExtend(array $inputs) 自定义扩展验证 $input为验证数据
 *
 * Trait ValidateTrait
 */
trait ValidateTrait
{
    /**
     * 数据验证
     * @param array $inputs 验证参数
     * @return array 验证参数
     */
    protected function validated(array $inputs): array
    {
        $validator = di(ValidatorFactoryInterface::class)->make($inputs, $this->rules(), $this->messages(), $this->attributes());
        if ($validator->fails()) {
            throw new ValidationException(ErrorCode::PARAMS_ERROR, $validator->errors()->first());
        }

        $extend = [];

        $params = $validator->validated() + $extend;

        //过滤空值
        foreach ($params as $key => $val) {
            if (isset($val) && empty($val) && $val != '0') {
                unset($params[$key]);
            }
        }
        //设置默认值
        foreach ($this->default() as $key => $val) {
            if (!isset($params[$key])) {
                $params[$key] = $val;
            }
        }

        return $params;
    }
}