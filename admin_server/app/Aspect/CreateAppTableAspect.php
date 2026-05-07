<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Annotation\CreateAppTable;
use Hyperf\DB\DB;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect
 */
class CreateAppTableAspect extends AbstractAspect
{
    public $annotations = [
        CreateAppTable::class,
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $result = $proceedingJoinPoint->process();
        $modelName = get_class($result);
        $createTable = AnnotationCollector::getMethodsByAnnotation(CreateAppTable::class);
        foreach ($createTable as $table){
            if (data_get($table, 'class') == $modelName){
                $baseTable = data_get($table, 'annotation.baseTableName');
                $dbConnect = data_get($table, 'annotation.dbConnect');
                DB::connection($dbConnect)->query("CREATE TABLE IF NOT EXISTS {$result->getTable()} LIKE {$baseTable};");
                break;
            }
        }
        // 在调用后进行某些处理
        return $result;
    }
}