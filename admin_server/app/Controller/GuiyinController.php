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

class GuiyinController extends AbstractController
{
    public function list(string $appCode)
    {
        $params = $this->request->all();

        $query = Db::table('report_guiyin')
            ->select([
                'date',
                Db::raw('sum(install) as install'),
                Db::raw('sum(install_new) as install_new'),
                Db::raw('sum(active) as active'),
                Db::raw('sum(active_new) as active_new'),
                Db::raw('sum(guiyin) as guiyin'),
                Db::raw('sum(guiyin_new) as guiyin_new'),
                Db::raw("if(sum(install) > 0,round(sum(guiyin)*100/sum(install),2),0) as rate_guiyin"),
                Db::raw("if(sum(install_new) > 0,round(sum(guiyin_new)*100/sum(install_new),2),0) as rate_guiyin_new"),
                Db::raw("if(sum(install) > 0,round(sum(install_param)*100/sum(install),2),0) as rate_self"),
                Db::raw("if(sum(install) > 0,round(sum(client)*100/sum(install),2),0) as rate_client"),
                Db::raw("if(sum(install) > 0,round(sum(black)*100/sum(install),2),0) as rate_black"),
                Db::raw("if(sum(jl_total) > 0,round(sum(jl_param)*100/sum(jl_total),2),0) as rate_jl"),
                Db::raw("if(sum(ks_total) > 0,round(sum(ks_param)*100/sum(ks_total),2),0) as rate_ks"),
            ])
            ->where('app_code', $appCode)
            ->when($dateBegin = data_get($params, 'date_begin'), function ($query) use ($dateBegin) {
                $query->where('date', '>=', $dateBegin);
            })
            ->when($dateEnd = data_get($params, 'date_end'), function ($query) use ($dateEnd) {
                $query->where('date', '<=', $dateEnd);
            })
            ->when($version = data_get($params, 'version'), function ($query) use ($version){
                $query->where('version', $version);
            });

        $result = $query->groupBy('date')->orderBy('date', 'desc')->get()->all();

        return $this->responseRecord(object_to_arr($result));
    }
}