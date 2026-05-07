<?php

declare(strict_types=1);

namespace App\Controller\Mkt;

use App\Controller\AbstractController;
use App\Request\Mkt\Auth\CallbackBdRequest;
use App\Request\Mkt\Auth\CallbackHwRequest;
use App\Request\Mkt\Auth\CallbackRequest;
use App\Request\Mkt\Auth\CallbackViRequest;
use App\Service\Mkt\MktService;
use App\Utils\Log;
use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;

class MktController extends AbstractController
{
    /**
     * 授权回调
     * @param CallbackRequest $request
     * @return ResponseInterface
     */
    public function auth(CallbackRequest $request)
    {
        $params = $request->all();

        try {
            make(MktService::class)->authUser((string)data_get($params, 'app_id'), (string)data_get($params, 'auth_code'), (string)data_get($params, 'user_id'));
        } catch (Exception $e) {
            return is_product() ? $this->responseError() : $this->responseError(-1, $e->getMessage());
        }

        return $this->responseSuccess();
    }

    /**
     * 授权回调
     * @param CallbackHwRequest $request
     * @return ResponseInterface
     */
    public function huawei(CallbackHwRequest $request)
    {
        $params = $request->all();

        try {
            make(MktService::class)->authUser((string)data_get($params, 'client_id'), (string)data_get($params, 'authorization_code'), (string)data_get($params, 'user_id'));
        } catch (Exception $e) {
            return is_product() ? $this->responseError() : $this->responseError(-1, $e->getMessage());
        }

        return $this->responseSuccess();
    }

    /**
     * 授权回调
     * @param CallbackViRequest $request
     * @return ResponseInterface
     */
    public function vivo(CallbackViRequest $request)
    {
        $params = $request->all();

        try {
            make(MktService::class)->authUser((string)data_get($params, 'clientId'), (string)data_get($params, 'code'),(string)data_get($params, 'userId'));
        } catch (Exception $e) {
            return is_product() ? $this->responseError() : $this->responseError(-1, $e->getMessage());
        }

        return $this->responseSuccess();
    }

    /**
     * 授权回调
     * @param CallbackBdRequest $request
     * @return ResponseInterface
     */
    public function baidu(CallbackBdRequest $request)
    {
        $params = $request->all();

        try {
            make(MktService::class)->authUser((string)data_get($params, 'appId'), (string)data_get($params, 'authCode'), (string)data_get($params, 'userId'));
        } catch (Exception $e) {
            return is_product() ? $this->responseError() : $this->responseError(-1, $e->getMessage());
        }

        return $this->responseSuccess();
    }

    /**
     * 订阅通知
     * @return ResponseInterface
     */
    public function notify()
    {
        $params = $this->request->all();

        Log::http('info', '【MKT-NOTIFY】', [
            'params' => $params,
        ]);

        if (!empty($params['challenge'])) {
            return $this->response->json([
                'BaseResp' => [
                    'StatusCode' => 200,
                    'StatusMessage' => 'ok',
                ],
                'challenge' => (int)($params['challenge'] ?? 0)
            ]);
        }

        if (!empty($params['message_id'])) {
            Db::table('mkt_notify')
                ->insert([
                    'json' => json_encode($params)
                ]);
        }

        return $this->responseSuccess();
    }

    public function income(string $token)
    {
        if ($token != '6486c293265cf') {
            return $this->responseSuccess();
        }

        $params = $this->request->all();

        $start = date('Y-m-d', strtotime(data_get($params, 'start', date('Y-m-d', strtotime('-1 day')))));
        $end = date('Y-m-d', strtotime(data_get($params, 'end', date('Y-m-d', strtotime('-1 day')))));

        $list = Db::SELECT("
            SELECT
                a.created_date,
                app.app_code,
                app.app_name,
                (CASE app.ad_status
                    WHEN 1 THEN '自投'
                    WHEN 2 THEN '代投'
                    ELSE '停投' 
                END) AS ad_status,
                sum( a.cost ) AS cost,
                sum( a.active_revenue ) AS revenue,
                sum( a.active_revenue ) / sum( a.cost ) AS roi,
                sum( b.revenue ) AS real_revenue,
                sum( b.revenue ) / sum( a.cost ) AS real_roi,
                concat( sum( a.active_revenue - b.revenue )* 100 / sum( b.revenue ), '%' ) AS revenue_gap 
            FROM
                report_daily_launch_base AS a
                LEFT JOIN ( SELECT app_code, sum( revenue ) AS revenue, date FROM media_income GROUP BY app_code, date ) AS b ON b.app_code = a.app_code 
                AND a.created_date = b.date
                LEFT JOIN app ON app.app_code = a.app_code 
            WHERE
                a.created_date >= '{$start}'
                AND a.created_date <= '{$end}'
            GROUP BY
                created_date,
                app_code
            ORDER BY
                created_date DESC,
                revenue DESC
        ");
        $Render = di(RenderInterface::class);

        $media = Db::SELECT("
            select distinct media_type from media_income where date = '{$end}'
        ");

        return $Render->render('report/media', [
            'list' => json_decode(json_encode($list), true),
            'media' => implode(',', array_column(json_decode(json_encode($media), true), 'media_type')),
            'date' => $end
        ]);
    }
}