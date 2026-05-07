<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Job\Task\TaskJob;
use App\Model\ReportTask;
use App\Request\App\EditAuthRequest;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class TaskController extends AbstractController
{
    public function save(string $appCode)
    {
        $params = $this->request->all();
        if (empty($params['time_end']) || empty($params['time_start']) || empty($params['type'])) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '参数错误');
        }

        if (strtotime($params['time_end']) <= strtotime($params['time_start'])) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '时间范围异常');
        }

        if (strtotime($params['time_end']) - strtotime($params['time_start']) > 24 * 3600) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '时间范围跨度不可超过24小时');
        }

        if ($params['type'] == 'strategy' && date('Ymd', strtotime($params['time_start'])) != date('Ymd', strtotime($params['time_end']) - 1)) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '时间范围不可跨天');
        }

        $info = [
            'time_start' => $params['time_start'],
            'time_end' => $params['time_end'],
            'type' => $params['type']
        ];
        $model = new ReportTask();
        $model->fill([
            'app_code' => $appCode,
            'info' => $info
        ])->save();

        queue_push(new TaskJob($model->id), 'task', 10);

        return $this->responseSuccess();
    }

    public function list(string $appCode)
    {
        $params = $this->request->all();
        $pageSize = intval($params['page_size'] ?? 10);
        $status = data_get($params, 'status', '');
        $query = ReportTask::query()
            ->select([
                'id',
                'info',
                'status',
                Db::raw("FROM_UNIXTIME(created_time,'%Y-%m-%d %H:%i:%s') as create_time")
            ])
            ->where('app_code', $appCode)
            ->when($status !== '', function ($query) use ($status) {
                return $query->where('status', $status);
            });
        $query->orderBy('id', 'desc');
        $paginate = $query->paginate($pageSize);

        return $this->responsePaginator($paginate->items(), $paginate);
    }

    public function detail(string $appCode)
    {
        $params = $this->request->all();
        $data = ReportTask::query()->where('app_code', $appCode)->where('id', $params['id'])->value('result');
        return $this->responseSuccess($data);
    }
}