<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\AppMediaApp;
use App\Request\UnionMedia\CreateBindAdSlotRequest;
use App\Request\UnionMedia\ExportCsjExcelRequest;
use App\Service\AdSlot\AdSlotService;
use App\Service\UnionMediaService;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;

class UnionMediaController extends AbstractController
{
    /**
     * @Inject
     * @var UnionMediaService
     */
    protected $unionMediaService;

    /**
     * @Inject
     * @var AdSlotService
     */
    protected $adSlotService;

    public function exportCsjExcel(ExportCsjExcelRequest $request)
    {
        $excelFile = $request->file('excel');
        $companyId = $request->input('company_id');
        $filePath =  $excelFile->getRealPath();
        $excelData = $this->unionMediaService->readerCsjExcel($filePath);
        $list = $this->unionMediaService->formatCsjExcelData(intval($companyId), $excelData);

        return $this->responseRecord($list);
    }

    public function bindCsj()
    {
        $params = $this->request->all();
        $companyId = data_get($params, 'company_id');
        $bindData = data_get($params, 'bind_data');
        $appCode = data_get($params, 'app_code');
        $result = $this->unionMediaService->bindCsj($companyId, $appCode, $bindData);

        return $this->responseSuccess($result);
    }

    public function bindListCsj(string $appCode)
    {
        $list = $this->unionMediaService->bindListCsj($appCode, $this->request->all());

        return $this->responsePaginator($list->items(), $list);
    }

    public function networkCsj(int $companyId)
    {
        $list = $this->unionMediaService->networkCsj($companyId);

        return $this->responseRecord($list);
    }

    public function accountCsj()
    {
        $list = $this->unionMediaService->accountCsj();

        return $this->responseRecord($list);
    }

    public function getCreateBindAdSlotStatus(string $execKey)
    {
        $result = $this->adSlotService->getCreateBindAdSlotStatus($execKey);
        return $this->responseSuccess($result);
    }

    /**
     * 创建绑定
     * @param CreateBindAdSlotRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function createBindAdSlot(CreateBindAdSlotRequest $request)
    {
        $excelFile = $request->file('excel');
        $extension = $excelFile->getExtension();
        $timePath = date('Ymd');
        $filePath =  BASE_PATH . "/runtime/upload/excel/{$timePath}/";
        if (!file_exists($filePath)) {
            // 创建文件夹
            mkdir($filePath, 0777, true);
            go(function () use ($filePath, $timePath) {
                delete_dir($filePath, $timePath);
            });
        }
        $execKey = create_uuid();
        $file = "{$filePath}/$execKey.{$extension}";
        $excelFile->moveTo($file);
        $this->adSlotService->createBindAdSlot($execKey, $file);
        return $this->responseSuccess(['exec_key' => $execKey]);
    }

    /**
     * 创建绑定结果
     * @param string $execKey
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function createBindList(string $execKey)
    {
        $list = $this->adSlotService->createBindList($execKey);

        return $this->responseRecord($list);
    }

    public function createRetry(int $id)
    {
        $this->adSlotService->createRetry($id);
        return $this->responseSuccess();
    }

    public function bindRetry(int $id)
    {
        $this->adSlotService->bindRetry($id);
        return $this->responseSuccess();
    }

    public function mediaApp()
    {
        $params = $this->request->all();
        $list = Db::table('app_media_app as mapp')
            ->leftJoin('app_company as c', 'c.company_id', '=', 'mapp.account_id')
            ->select(['mapp.account_id', 'mapp.app_id', 'mapp.platform', 'mapp.app_name', 'mapp.package_name', 'c.company_name'])
            ->when(data_get($params, 'platform'), function ($query, $platform) {
                $query->where('mapp.platform', $platform);
            })
            ->orderBy('mapp.id', 'desc')
            ->paginate(intval(data_get($params, 'per_page', 10)));

        return $this->responsePaginator($list->items(), $list);
    }

    public function saveMediaApp()
    {
        $params = $this->request->all();
        $model = new AppMediaApp();
        $model->fill([
            'account_id' => data_get($params, 'account_id'),
            'app_id' => data_get($params, 'app_id'),
            'platform' => data_get($params, 'platform'),
            'app_name' => data_get($params, 'app_name'),
            'package_name' => data_get($params, 'package_name'),
        ]);
        $model->saveOrFail();

        return $this->responseSuccess();
    }
}