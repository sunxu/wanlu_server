<?php

declare (strict_types=1);
namespace App\ModelTools;

use Hyperf\DbConnection\Model\Model;
/**
 * @property int $id 
 * @property string $name 渠道名
 */
class AppChannel extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_channel';
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tools';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer'];
}