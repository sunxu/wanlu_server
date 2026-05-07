<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\Events\Creating;
use Hyperf\Database\Model\Events\Updating;

/**
 * @property string $app_code app标识
 * @property int $variable1 估算比例次日（%）
 * @property int $variable2 估算比例2日（%）
 * @property int $variable3 估算比例3日（%）
 * @property int $variable4 估算比例4日（%）
 * @property int $variable5 估算比例5日（%）
 * @property int $variable6 估算比例6日（%）
 * @property int $variable7 估算比例7日（%）
 * @property int $created_time 创建时间
 * @property int $updated_time 修改时间
 */
class RevenueMockVariable extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public $timestamps = true;
    protected $dateFormat = 'U';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'revenue_mock_variable';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'app_code', 'variable1', 'variable2', 'variable3', 'variable4', 'variable5', 'variable6', 'variable7', 'created_time', 'updated_time'
    ];

    public function creating(Creating $event)
    {
        $this->setAttribute('created_time', time());
        $this->setAttribute('updated_time', time());
    }

    public function updating(Updating $event)
    {
        $this->setAttribute('updated_time', time());
    }
}