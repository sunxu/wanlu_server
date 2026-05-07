<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Constants\AppStoreConst;
use App\Library\AppStore\Huawei;
use App\Library\AppStore\Oppo;
use App\Library\AppStore\Vivo;
use App\Library\AppStore\Xiaomi;
use App\Library\Excel\ReaderExcel;
use App\Library\HttpClient\HttpClient;
use App\Library\Media\MediaBD;
use App\Model\Account;
use App\Model\App;
use App\Model\AppCompany;
use App\Model\Mkt\ReportDailyAdvertiser;
use App\Model\ReportDailyLaunchBase;
use App\Service\AdSlot\AdSlotService;
use App\Service\AppService;
use App\Service\Gains\Music\QuestionService;
use App\Service\Launch\AdService;
use App\Service\Launch\LaunchService;
use App\Service\Mkt\AutoPlanService;
use App\Service\Paid\PaidService;
use App\Service\Placement\LaunchBaseService;
use App\Service\Product\ProductService;
use App\Service\UnionMediaService;
use App\Service\UserService;
use App\Utils\Ding;
use App\Utils\RedisManager;
use Carbon\Carbon;
use Hyperf\Context\Context;
use Hyperf\DbConnection\Db;
use HyperfTest\HttpTestCase;
use PHPUnit\Exception;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends HttpTestCase
{
    public function testExample()
    {
        $requestParams = [
            'page_no' => 1,
            "page_size" => 2,
            "ad_types"=>[
                  52
             ]
        ];
        $res = HttpClient::connect('Ubapi', 'Position')->setAccount(101)->add($requestParams);

//        $conf = AppCompany::select('media_conf_bd')->where('company_id', 101)->first();
//        $conf = data_get($conf, 'media_conf_bd');
//        $res = make(MediaBD::class, [$conf])->testquery($requestParams);
        var_dump($res);
        $this->assertTrue(true);
        $this->assertTrue(is_array($this->get('/')));
    }
}
