<?php
declare(strict_types=1);

namespace App\Controller\Target;
use App\Controller\AbstractController;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;

class RatioController extends AbstractController{

    public function index(RequestInterface $request){
        $timeSlot = $request->query('startTime');
        if(empty($timeSlot)){
            $start_date = strtotime(date('Y-m-d',time()));
            $end_date = strtotime(date('Y-m-d',time()))+86400-1;
        }else{
            $start_date = strtotime(explode('T',$timeSlot[0])[0]);
            $end_date = strtotime(explode('T',$timeSlot[1])[0])+86400-1;
        }
        $appcode = $request->query('appcode', 'wifi');
        $page = $request->query('page',1);
        $size = $request->query('limit',20);
        $where = " where app_code='{$appcode}'";
        $sql = "select * from ad_level_num
         $where
        and  UNIX_TIMESTAMP(date)  >= {$start_date}
        and   UNIX_TIMESTAMP(date)  < {$end_date}
        order by UNIX_TIMESTAMP(date) desc ";
        $sql = "($sql) as report";
        $data_all = Db::connection('tools')->table(Db::raw($sql))->get()->toArray();
        $count = count($data_all);
        $data = array_slice($data_all, ($page - 1) * $size, $size);
        $data = json_decode(json_encode($data), true);
        foreach ($data as &$v) {
            //无任意层广告展示
            $v['rate_none'] = $v['activate'] == 0 ? 0 : ($v['level_none'] / $v['activate']) * 100;
            $v['rate_none'] = round($v['rate_none'], 2);
            //只有兜底层
            $v['rate_zero'] = $v['activate'] == 0 ? 0 : ($v['level_0'] / $v['activate']) * 100;
            $v['rate_zero'] = round($v['rate_zero'], 2);
            //保价1层
            $v['rate_one'] = $v['activate'] == 0 ? 0 : ($v['level_1'] / $v['activate']) * 100;
            $v['rate_one'] = round($v['rate_one'], 2);
            //保价2层
            $v['rate_two'] = $v['activate'] == 0 ? 0 : ($v['level_2'] / $v['activate']) * 100;
            $v['rate_two'] = round($v['rate_two'], 2);
            //保价3层
            $v['rate_three'] = $v['activate'] == 0 ? 0 : ($v['level_3'] / $v['activate']) * 100;
            $v['rate_three'] = round($v['rate_three'], 2);
        }
        return $this->responseSuccess(array('list'=>$data,'total'=>(int)$count,'page'=>(int)$page,'limit'=>(int)$size));
    }
}
