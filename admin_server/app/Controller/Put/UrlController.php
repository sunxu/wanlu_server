<?php

declare(strict_types=1);

namespace App\Controller\Put;

use App\Controller\AbstractController;
use App\Model\App;
use App\Model\AppCompany;
use App\Service\AppService;

class UrlController extends AbstractController
{
    public function index(string $appCode)
    {
        $app = App::query()->where('app_code', $appCode)->first(['app_name', 'company_id']);
        $appName = $app->app_name;
        if (!$appName) {
            return $this->responseError();
        }
        $config = config('ad');
        $domain = AppCompany::query()->where('company_id', $app->company_id)->value('domain');
        if (empty($domain)) {
            return $this->responseError();
        }
        $baseUrl = $domain ? str_replace('{domain}', $domain, $config['base_url']) : $config['base_url'];
        $list = [];
        foreach ($config['source'] ?? [] as $item) {
            $list[] = [
                'name' => $item['name'],
                'url' => $baseUrl . sprintf($item['path'], $appCode, urldecode(http_build_query($item['params'])))
            ];
        }
        $appService = make(AppService::class);
        return $this->responseSuccess([
            'list' => $list,
            'app_name' => $appName,
            'put' => $appService->getPutStatus($appCode),
            'channel' => $appService->getExtra($appCode, 'block_channel', [])
        ]);
    }
}