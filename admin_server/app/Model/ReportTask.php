<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * @property int $id
 * @property string $app_code 应用
 * @property int $status [0]进行中[1]完成
 * @property string $info 任务信息
 * @property string $result 任务结果
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 */
class ReportTask extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';
    protected $primaryKey = 'id';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'report_task';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['app_code', 'status', 'info', 'result'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'status' => 'integer', 'created_time' => 'integer', 'updated_time' => 'integer', 'info' => 'array', 'result' => 'array'];
}