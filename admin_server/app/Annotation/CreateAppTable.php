<?php

declare(strict_types=1);

namespace App\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class CreateAppTable extends AbstractAnnotation
{
    /**
     * 数据库
     * @var string
     */

    public $dbConnect = 'default';
    /**
     * 基础表名
     * @var string
     */
    public $baseTableName;
}