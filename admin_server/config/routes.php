<?php

declare(strict_types=1);

use App\Controller\AppController;
use App\Controller\Cash\EcpmController;
use App\Controller\Cash\LiveController;
use App\Controller\Cash\RevenueController;
use App\Controller\Cash\ShowController;
use App\Controller\CompanyController;
use App\Controller\DelegateController;
use App\Controller\GuiyinController;
use App\Controller\Launch\LaunchAdController;
use App\Controller\Launch\LaunchController;
use App\Controller\Media\TotalController;
use App\Controller\Mkt\CommonController;
use App\Controller\Paid\FeedbackController;
use App\Controller\Paid\GoodsController;
use App\Controller\Paid\PaidController;
use App\Controller\Product\ProductController;
use App\Controller\Put\AlertDelegateController;
use App\Controller\Put\AlertRuleController;
use App\Controller\Put\AutoAppController;
use App\Controller\Put\AutoAppControllerV2;
use App\Controller\Put\AutoPlanController;
use App\Controller\Put\AutoPlanControllerV2;
use App\Controller\RoleController;
use App\Controller\TaskController;
use App\Controller\UnionMediaController;
use App\Controller\UserController;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\AppAuthMiddleware;
use App\Middleware\LoginMiddleware;
use App\Middleware\SignMiddleware;
use Hyperf\HttpServer\Router\Router;
use Hyperf\Validation\Middleware\ValidationMiddleware;

/*
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */


//不校验登录
Router::addGroup('/uc', function () {
    Router::addGroup('/{version}', function () {
        //注册
        Router::post('/register', [UserController::class, 'register']);
        //登录
        Router::post('/login', [UserController::class, 'login']);
    });
    Router::get('/mis/inner/withdrawal/qrcode/{merchantId}/{appId}', [DelegateController::class, 'mis']);

}, [
    'middleware' => [
        SignMiddleware::class,
        ValidationMiddleware::class
    ]
]);

//校验登陆不校验权限 通用接口
Router::addGroup('/uc', function () {
    Router::addRoute(['GET', 'POST', 'PUT', 'DELETE'], '/mis/{all:.*}', [DelegateController::class, 'mis'], ['middleware' => [ApiAuthMiddleware::class]]);
    Router::addGroup('/{version}', function () {
        //登录用户详情
        Router::get('/selfInfo', [UserController::class, 'selfInfo']);
        //登出
        Router::post('/logout', [UserController::class, 'logout']);
        //修改密码
        Router::put('/self/password', [UserController::class, 'editSelfPassword']);

        Router::addGroup('/mkt', function () {
            Router::get('/tx/applications', [App\Controller\Mkt\MktController::class, 'tencentApplications']);
            Router::post('/tx/authorize', [App\Controller\Mkt\MktController::class, 'tencentAuthorize']);
            Router::get('/{appCode}/advertiser/project/ad', [CommonController::class, 'getAdvertiserProjectAd']);
            Router::get('/{appCode}/advertiser/project', [CommonController::class, 'getAdvertiserProject']);
            Router::get('/{appCode}/advertiser/plan', [CommonController::class, 'getAdvertiserWithPlan']);
            Router::get('/{appCode}/advertiser', [CommonController::class, 'getAdvertisers']);
            Router::get('/{appCode}/agency', [CommonController::class, 'getAgencys']);
        });
        Router::get('/company', [CompanyController::class, 'list']);

        Router::post('/launch/hourly_ad_filter', [App\Controller\JsonController::class, 'hourly_ad_filter']);
        Router::post('/launch/hourly_filter', [App\Controller\JsonController::class, 'hourly_filter']);
    });
}, [
    'middleware' => [
        SignMiddleware::class,
        LoginMiddleware::class,
        ValidationMiddleware::class
    ]
]);
//校验登录
Router::addGroup('/uc', function () {
    Router::addGroup('/{version}', function () {
        //app管理
        Router::get('/app', [AppController::class, 'index']);
        Router::get('/app/all', [AppController::class, 'appList']);
        Router::put('/app/{appId}/auth', [AppController::class, 'editAuth']);
        Router::put('/app/{appId}/status', [AppController::class, 'editStatus']);
        Router::put('/app/{appId}/putStatus', [AppController::class, 'editPutStatus']);
        Router::put('/app/{appId}/putStatus2', [AppController::class, 'editPutStatus2']);
        Router::put('/app/save', [AppController::class, 'saveData']);
        Router::put('/app/{appCode}/block', [AppController::class, 'block']);
        Router::put('/app/{appCode}/put', [AppController::class, 'putStatus']);

        //用户管理
        Router::get('/user', [UserController::class, 'index']);
        Router::get('/user/{userId}', [UserController::class, 'detail']);
        Router::post('/user', [UserController::class, 'store']);
        Router::put('/user/{userId}', [UserController::class, 'edit']);
        Router::put('/user/{userId}/password', [UserController::class, 'editPassword']);
        Router::put('/user/{userId}/status', [UserController::class, 'editStatus']);

        //角色管理
        Router::get('/role', [RoleController::class, 'index']);
        Router::get('/role/{roleId}', [RoleController::class, 'detail']);
        Router::post('/role', [RoleController::class, 'store']);
        Router::put('/role/{roleId}', [RoleController::class, 'edit']);
    });
}, [
    'middleware' => [
        SignMiddleware::class,
        LoginMiddleware::class,
        ValidationMiddleware::class,
        ApiAuthMiddleware::class
    ]
]);

//校验登录 业务报表 校验 app权限
Router::addGroup('/uc', function () {
    Router::addGroup('/{version}', function () {
        //通用
        Router::addGroup('', function () {
            Router::post('/placement/csj/export', [UnionMediaController::class, 'exportCsjExcel']);
            Router::post('/placement/csj/bind', [UnionMediaController::class, 'bindCsj']);
            Router::get('/placement/csj/network/{companyId}', [UnionMediaController::class, 'networkCsj']);
            Router::get('/placement/csj/account', [UnionMediaController::class, 'accountCsj']);
            Router::addGroup('/launch', function () {//推广数据报表
                Router::get('/hour/chart', [LaunchController::class, 'hourLaunchChart']); //广告列表
            });
            Router::get('/product/dailyBase', [ProductController::class, 'dailyBase']);
            Router::get('/revenue/app', [TotalController::class, 'app']);
            Router::get('/revenue/company', [TotalController::class, 'company']);
        });
        //校验app
        Router::addGroup('/{appCode}', function () {
            //变现数据报表
            Router::addGroup('/cash', function () {
                Router::get('/live/{type}', [LiveController::class, 'index']);
                Router::get('/ecpm', [EcpmController::class, 'index']);
                Router::get('/revenue', [RevenueController::class, 'index']);
                Router::get('/show/{type}', [ShowController::class, 'index']);
                Router::get('/revenue/mock', [RevenueController::class, 'mockRevenue']);
                Router::get('/revenue/mock/variables', [RevenueController::class, 'mockVariables']);
                Router::put('/revenue/mock/variables', [RevenueController::class, 'editMockVariables']);
            });
            Router::addGroup('/task', function () {
                Router::post('/save', [TaskController::class, 'save']);
                Router::get('/list', [TaskController::class, 'list']);
                Router::get('/detail', [TaskController::class, 'detail']);
            });
            router::addGroup('/placement', function () {
                Router::get('/csj', [UnionMediaController::class, 'bindListCsj']);
                Router::post('/adslot', [UnionMediaController::class, 'createBindAdSlot']);
                Router::get('/adslot/{execKey}', [UnionMediaController::class, 'getCreateBindAdSlotStatus']);
                Router::get('/adslot/{execKey}/result', [UnionMediaController::class, 'createBindList']);
                Router::post('/adslot/{id}/create/retry', [UnionMediaController::class, 'createRetry']);
                Router::post('/adslot/{id}/bind/retry', [UnionMediaController::class, 'bindRetry']);
                Router::get('/adslot/media/app', [UnionMediaController::class, 'mediaApp']);
                Router::post('/adslot/media/app', [UnionMediaController::class, 'saveMediaApp']);
            });
            Router::addGroup('/launch', function () {//推广数据报表
                Router::get('/daily/base', [LaunchController::class, 'dailyLaunchList']); //广告列表
            });
            Router::addGroup('/put', function () {//推广投放管理
                Router::post('/plan/auto', [AutoPlanController::class, 'savePlan']);
                Router::post('/plan/auto/conflict', [AutoPlanController::class, 'checkPlanConflict']);
                Router::post('/plan/auto/list', [AutoPlanController::class, 'planList']);
                Router::get('/plan/auto/{planId}', [AutoPlanController::class, 'planInfo']);
                Router::put('/plan/auto/{planId}', [AutoPlanController::class, 'editPlan']);
                Router::put('/plan/auto/{planId}/enable', [AutoPlanController::class, 'enablePlan']);
                Router::get('/plan/auto/{planId}/log', [AutoPlanController::class, 'planLogs']);
                Router::get('/plan/auto/{planId}/log/{logId}', [AutoPlanController::class, 'planLogInfo']);

                Router::post('/app/auto', [AutoAppController::class, 'savePlan']);
                Router::post('/app/auto/list', [AutoAppController::class, 'planList']);
                Router::get('/app/auto/{planId}', [AutoAppController::class, 'planInfo']);
                Router::put('/app/auto/{planId}', [AutoAppController::class, 'editPlan']);
                Router::put('/app/auto/{planId}/enable', [AutoAppController::class, 'enablePlan']);
                Router::get('/app/auto/{planId}/log', [AutoAppController::class, 'planLogs']);
                Router::get('/app/auto/{planId}/log/{logId}', [AutoAppController::class, 'planLogInfo']);

                Router::post('/plan/autov2', [AutoPlanControllerV2::class, 'savePlan']);
                Router::post('/plan/autov2/conflict', [AutoPlanControllerV2::class, 'checkPlanConflict']);
                Router::post('/plan/autov2/list', [AutoPlanControllerV2::class, 'planList']);
                Router::get('/plan/autov2/{planId}', [AutoPlanControllerV2::class, 'planInfo']);
                Router::put('/plan/autov2/{planId}', [AutoPlanControllerV2::class, 'editPlan']);
                Router::put('/plan/autov2/{planId}/enable', [AutoPlanControllerV2::class, 'enablePlan']);
                Router::get('/plan/autov2/{planId}/log', [AutoPlanControllerV2::class, 'planLogs']);
                Router::get('/plan/autov2/{planId}/log/{logId}', [AutoPlanControllerV2::class, 'planLogInfo']);

                Router::post('/app/autov2', [AutoAppControllerV2::class, 'savePlan']);
                Router::post('/app/autov2/conflict', [AutoAppControllerV2::class, 'checkPlanConflict']);
                Router::post('/app/autov2/list', [AutoAppControllerV2::class, 'planList']);
                Router::get('/app/autov2/{planId}', [AutoAppControllerV2::class, 'planInfo']);
                Router::put('/app/autov2/{planId}', [AutoAppControllerV2::class, 'editPlan']);
                Router::put('/app/autov2/{planId}/enable', [AutoAppControllerV2::class, 'enablePlan']);
                Router::get('/app/autov2/{planId}/log', [AutoAppControllerV2::class, 'planLogs']);
                Router::get('/app/autov2/{planId}/log/{logId}', [AutoAppControllerV2::class, 'planLogInfo']);

                Router::addGroup('/alert-rule', function () {//报警规则
                    Router::post('', [AlertRuleController::class, 'saveRule']);
                    Router::post('/list', [AlertRuleController::class, 'ruleList']);
                    Router::get('/{ruleId}', [AlertRuleController::class, 'ruleInfo']);
                    Router::put('/{ruleId}', [AlertRuleController::class, 'updateRule']);
                    Router::put('/{ruleId}/enable', [AlertRuleController::class, 'enableRule']);
                    Router::delete('/{ruleId}', [AlertRuleController::class, 'deleteRule']);
                });

            });
            // 产品数据报表
            Router::addGroup('/product', function () {
                Router::get('/deviceecpmevent', [ProductController::class, 'deviceEcpmEventList']);
                Router::get('/deviceecpmevent/condition', [ProductController::class, 'deviceEcpmEventCondition']);
            });
            Router::get('/guiyin/list', [GuiyinController::class, 'list']);
        }, ['middleware' => [AppAuthMiddleware::class,]]);
    });
}, [
    'middleware' => [
        SignMiddleware::class,
        LoginMiddleware::class,
        ValidationMiddleware::class,
        ApiAuthMiddleware::class
    ]
]);

$middleware = is_local() ? [] : [
    SignMiddleware::class,
    LoginMiddleware::class,
    ValidationMiddleware::class,
    ApiAuthMiddleware::class
];
Router::addGroup('/uc', function () {
    Router::addGroup('/{version}', function () {
        Router::addGroup('/product', function () {//产品数据报表
            Router::post('/advertiser', [\App\Controller\Product\AdvertiserAction::class, 'handle']);//广告主列表
            Router::post('/keep', [\App\Controller\Product\KeepAction::class, 'handle']);//留存用户
            Router::post('/vendor', [\App\Controller\Product\VendorAction::class, 'handle']);//厂商分布
        });
        Router::addGroup('/cash', function () {//变现数据报表
            Router::post('/cashList', [\App\Controller\CashController::class, 'cashList']); //心跳列表
            Router::post('/show', [\App\Controller\CashController::class, 'averageShow']); //人均列表
        });
        Router::addGroup('/launch', function () {//推广数据报表
            Router::post('/getAdVer', [\App\Controller\Launch\LadController::class, 'getAdvertiser']); //广告主列表
            Router::post('/getAdPlan', [\App\Controller\Launch\LadController::class, 'getAdvertiserPlan']); //广告计划名称
            Router::post('/adList', [\App\Controller\Launch\LadController::class, 'ad']); //广告主列表
            Router::post('/changeStatus', [\App\Controller\Launch\LadController::class, 'changeStatus']); //广告计划状态改变
            Router::post('/planList', [\App\Controller\Launch\LadController::class, 'plan']); //广告计划列表
            Router::post('/changePlanStatus', [\App\Controller\Launch\LadController::class, 'changePlanStatus']); //广告计划状态改变
            Router::post('/adListNew', [\App\Controller\Launch\LadController::class, 'adNew']); //新广告主列表
            Router::post('/planListNew', [\App\Controller\Launch\LadController::class, 'planNew']); //新广告计划列表
            Router::post('/longKeep', [\App\Controller\Launch\LadController::class, 'longKeep']); //激活留存 广告主
            Router::post('/longPlan', [\App\Controller\Launch\LadController::class, 'longPlan']); //激活留存 广告计划
            Router::post('/ad', [LaunchAdController::class, 'ad']); //广告列表
            Router::post('/project', [LaunchAdController::class, 'project']); //广告项目列表
            Router::post('/advertiser', [LaunchAdController::class, 'advertiser']); //广告主列表
            Router::post('/plan', [LaunchAdController::class, 'plan']); //广告计划列表
            Router::post('/ad/keep', [LaunchAdController::class, 'adKeep']); //广告项目列表
            Router::post('/project/keep', [LaunchAdController::class, 'projectKeep']); //广告项目列表
            Router::post('/plan/keep', [LaunchAdController::class, 'planKeep']); //广告主列表
            Router::post('/advertiser/keep', [LaunchAdController::class, 'advertiserKeep']); //广告计划列表
            Router::post('/total', [LaunchAdController::class, 'total']); //投放整体基础数据

            Router::post('/ad_performance', [App\Controller\JsonController::class, 'ad_performance']);
            Router::post('/report_advertiser', [App\Controller\JsonController::class, 'report_advertiser']);
            Router::post('/hourly_ad', [App\Controller\JsonController::class, 'hourly_ad']);
            Router::post('/hourly_advertiser', [App\Controller\JsonController::class, 'hourly_advertiser']);
            Router::post('/hourly_project', [App\Controller\JsonController::class, 'hourly_project']);
        });
        Router::addGroup('/ad', function () {//变现广告管理
            Router::addGroup('/placement', function () {//创建代码位
                Router::post('/account-list', [\App\Controller\Ad\Placement\AccountListAction::class, 'handle']);//主体账号
                Router::post('/app-list', [\App\Controller\Ad\Placement\AppListAction::class, 'handle']);//应用列表
                Router::post('/app-query', [\App\Controller\Ad\Placement\AppQueryAction::class, 'handle']);//查询应用
                Router::post('/upload', [\App\Controller\Ad\Placement\UploadAction::class, 'handle']);//上传代码位
                Router::post('/create-placement', [\App\Controller\Ad\Placement\CreatePlacementAction::class, 'handle']);//创建代码位
                Router::post('/bind-topon', [\App\Controller\Ad\Placement\BindToponAction::class, 'handle']);//创建代码位
            });
            Router::addGroup('/placement-map', function () {//代码位映射表
                Router::post('/list', [\App\Controller\Ad\PlacementMap\ListAction::class, 'handle']);//列表
                Router::post('/delete', [\App\Controller\Ad\PlacementMap\DeleteAction::class, 'handle']);//删除
                Router::post('/save', [\App\Controller\Ad\PlacementMap\SaveAction::class, 'handle']);//保存
                Router::post('/export', [\App\Controller\Ad\PlacementMap\ExportAction::class, 'handle']);//导出
                Router::post('/import', [\App\Controller\Ad\PlacementMap\ImportAction::class, 'handle']);//导入
            });
            Router::addGroup('/strategy', function () {//广告策略管理
                Router::post('/list', [\App\Controller\Ad\Strategy\ListAction::class, 'handle']);//列表
                Router::post('/save', [\App\Controller\Ad\Strategy\SaveAction::class, 'handle']);//保存
                Router::post('/delete', [\App\Controller\Ad\Strategy\DeleteAction::class, 'handle']);//删除
                Router::post('/status', [\App\Controller\Ad\Strategy\StatusAction::class, 'handle']);//删除
            });
            Router::addGroup('/ascribe', function () {//测试归因设置
                Router::post('/query', [\App\Controller\Ad\Ascribe\QueryAction::class, 'handle']);//设备查询
                Router::post('/save', [\App\Controller\Ad\Ascribe\SaveAction::class, 'handle']);//归因保存
            });
        });
        Router::addGroup('/target', function () {//指标比例
            Router::post('/ratio', [\App\Controller\Target\RatioController::class, 'index']);//指标比例列表
        });

        Router::addGroup('/mkt', function () {
            Router::post('/getAdvertiser', [\App\Controller\Mkt\CommonController::class, 'getAdvertiser']);
            Router::post('/getAdvertiserPlan', [\App\Controller\Mkt\CommonController::class, 'getAdvertiserPlan']);

        });

        Router::addGroup('/put', function () {//推广投放管理
            Router::addGroup('/alert-delegate', function () {//报警代投配置
                Router::post('', [AlertDelegateController::class, 'saveDelegate']);
                Router::post('/list', [AlertDelegateController::class, 'delegateList']);
                Router::get('/{delegateId}', [AlertDelegateController::class, 'delegateInfo']);
                Router::put('/{delegateId}', [AlertDelegateController::class, 'updateDelegate']);
                Router::delete('/{delegateId}', [AlertDelegateController::class, 'deleteDelegate']);
                Router::post('/{delegateId}/test', [AlertDelegateController::class, 'testDing']);
            });

            Router::addGroup('/advertiser', function () {//广告主管理
                Router::post('/list', [\App\Controller\Put\Advertiser\ListAction::class, 'handle']);//列表
                Router::post('/save', [\App\Controller\Put\Advertiser\SaveAction::class, 'handle']);//保存
                Router::post('/bind-app', [\App\Controller\Put\Advertiser\BindAppAction::class, 'handle']);//绑定应用
                Router::post('/status', [\App\Controller\Put\Advertiser\StatusAction::class, 'handle']);
            });

            Router::addGroup('/plan-rule', function () {//计划规则
                Router::post('/list', [\App\Controller\Put\PlanRule\ListAction::class, 'handle']);//列表
                Router::post('/save', [\App\Controller\Put\PlanRule\SaveAction::class, 'handle']);//保存
                Router::post('/bind', [\App\Controller\Put\PlanRule\BindAction::class, 'handle']);//绑定
                Router::post('/status', [\App\Controller\Put\PlanRule\StatusAction::class, 'handle']);//上下架
                Router::post('/delete', [\App\Controller\Put\PlanRule\DeleteAction::class, 'handle']);//删除
            });

            Router::post('/url/{appCode}', [\App\Controller\Put\UrlController::class, 'index']);
        });

        Router::addGroup('/paid', function () {
            Router::get('/goods', [GoodsController::class, 'index']);
            Router::post('/goods', [GoodsController::class, 'store']);
            Router::put('/goods/{goodsNo}', [GoodsController::class, 'edit']);
            Router::put('/goods/{goodsNo}/enable', [GoodsController::class, 'enable']);

            Router::get('/feedback', [FeedbackController::class, 'index']);

            Router::get('/report/daily/recharge', [PaidController::class, 'reportDailyList']);
        });
    });
}, [
    'middleware' => $middleware
]);

//钉钉日报html模板
Router::addGroup('/uc', function () {
    Router::get('/ding-daily/avg-show', [App\Controller\DingDaily\AvgShowAction::class, 'handle']);
    Router::get('/ding-daily/keep', [App\Controller\DingDaily\KeepAction::class, 'handle']);
    Router::get('/ding-daily/duration', [App\Controller\DingDaily\DurationAction::class, 'handle']);
});

Router::addGroup('/uc', function () {
    Router::get('/mkt/auth', [App\Controller\Mkt\MktController::class, 'auth']);
    Router::addRoute(['GET', 'POST'], '/mkt/notify', [App\Controller\Mkt\MktController::class, 'notify']);
    Router::get('/transverter/{key}', [App\Controller\RransverterController::class, 'index']);

    Router::get('/mkt/huawei', [App\Controller\Mkt\MktController::class, 'huawei']);
    Router::get('/mkt/vivo', [App\Controller\Mkt\MktController::class, 'vivo']);
    Router::get('/mkt/baidu', [App\Controller\Mkt\MktController::class, 'baidu']);
    Router::get('/mkt/tencent', [App\Controller\Mkt\MktController::class, 'tencent']);

    Router::get('/mkt/income/{token}', [App\Controller\Mkt\MktController::class, 'income']);
});
//外部接口
Router::get('/uc/app/{appCode}/secret/{token}', [AppController::class, 'getAppSecretKey']);
