<?php
declare(strict_types=1);
/**
 * 后缀分表基类
 * Author: jyk-wsy
 * Date: 2022/4/2
 */

namespace App\Utils;

use Hyperf\Database\Model\Builder;
use Hyperf\Database\Model\Model;

class SuffixModel extends Model
{
    /**
     * 设置表名后缀
     * @param string $suffix 表名后缀
     * @return SuffixModel
     */
    public function setSuffix(string $suffix = ''): SuffixModel
    {
        $this->table = str_replace('{suffix}', $suffix, $this->table);
        return $this;
    }

    /**
     * 构造查询对象
     * @param string $suffix 表名后缀
     * @return Builder
     */
    public static function query(string $suffix = ''): Builder
    {
        $instance = new static;
        $instance->setSuffix($suffix);
        return $instance->newQuery();
    }
}