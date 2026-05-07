<?php

declare(strict_types=1);

namespace App\Controller\Launch;

use App\Controller\AbstractController;
use App\Model\Mkt\MktAdvertiser;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;

class LadController extends AbstractController
{
    //通过应用获取广告主名称
    public function getAdvertiser(RequestInterface $request)
    {
        $app_code = $request->input('appcode');
        $plat = $request->input('plat');
        //查询应用下面的广告主
        $query = MktAdvertiser::where('app_code', $app_code);
        if ($plat == 2) {
            $query->where('platform', 'ks');
        } else if ($plat == 1) {
            $query->where('platform', 'jl');
        }
        $advertisers = $query->get()->all();
        $advertisers = json_decode(json_encode($advertisers), true);
        return $this->responseSuccess(['list' => $advertisers]);
    }

    //获取广告计划
    public function getAdvertiserPlan(RequestInterface $request)
    {
        $app_code = $request->input('appcode'); //应用类型
        $advertis_id = $request->input('advertiser'); //类型
//        var_dump($advertis_id);
        $where['a.app_code'] = $app_code;
        if (!empty($advertis_id)) {
            if (is_array($advertis_id)) {
//                $where['advertiser_id'] = ['in',implode(',',$advertis_id)];
                $where[] = [function ($query) use ($advertis_id) {
                    $query->whereIn('a.advertiser_id', $advertis_id);
                }];
            } else {
                $where['a.advertiser_id'] = $advertis_id;
            }
        }
        //查询应用下面的广告计划
        $advertisers_plan = Db::table('report_daily_plan as a')
            ->leftjoin('mkt_plan as mp', 'mp.plan_id', '=', 'a.plan_id')
            ->where($where)->groupBy('a.app_code', 'a.advertiser_id', 'a.plan_id')
            ->pluck('mp.plan_name', 'mp.plan_id')->all();

        return $this->responseSuccess(['list' => $advertisers_plan]);
    }

    public function ad(RequestInterface $request)
    {
        $timeSection = $request->query('startTime');
        $start_date = strtotime(date('Y-m-d', time()));
        $end_date = strtotime(date('Y-m-d', time())) + 60 * 60 * 24 - 1;
        if ($timeSection) {
            list($start_date, $end_date) = $timeSection;
            $start_date = strtotime(explode('T', $start_date)[0]);
            $end_date = strtotime(explode('T', $end_date)[0]) + 60 * 60 * 24 - 1;
        }

        $app_code = $request->query('appcode', 'wifi');
        $launch_type = $request->query('launch_type');  //1 关键行为 0 双出
        $plat = $request->query('plat');  //1 巨量 2 快手
        $advertiser_ids = $request->query('advertiser');
        $page = $request->query('page', 1);
        $size = $request->query('limit', 20);
        $sort = $request->query('sort');
        $order = $request->query('order');

        $order_sql = "";
        $sort_arr = ['install_pre_cost', 'active_pre_cost', 'install_roi', 'activate_roi', 'install_roi_24h', 'activate_roi_24h', 'install_withdraw', 'activate_withdraw', 'install_roi_withdraw', 'activate_roi_withdraw'];
        if (!empty($order) && !in_array($order, $sort_arr)) {
            if ($sort == 2) {
                $order_sql = ",a.{$order} desc";
            } else {
                $order_sql = ",a.{$order} asc";
            }
        }

        $where = " and a.app_code = '{$app_code}'";
        if ($plat == 2) {
            $where .= " and a.platform = 'ks'";
        } else if ($plat == 1) {
            $where .= " and a.platform = 'jl'";
        }

        if ($launch_type == 1) {
            $where .= ' and b.launch_type =1';
        } elseif ($launch_type == 2) {
            $where .= ' and b.launch_type =0';
        }
        if (!empty($advertiser_ids)) {
            $ad_str = '';
            foreach ($advertiser_ids as $ad) {
                $ad_str .= "'{$ad}',";
            }
            $ad_str = rtrim($ad_str, ',');
            $where .= " and a.advertiser_id in ({$ad_str})";
        }

        $sql = "select a.*,
b.advertiser_name,c.*  from report_daily_advertiser a 
join mkt_advertiser b on a.advertiser_id = b.advertiser_id
left join report_daily_advertiser_ext c on a.id = c.id
        where UNIX_TIMESTAMP(a.date)  >= {$start_date}
        and   UNIX_TIMESTAMP(a.date)  < {$end_date}
        $where
        order by UNIX_TIMESTAMP(a.date)  desc
        $order_sql
        ";
        //计算数据和
        $total_data = DB::select($sql);
        $total_data = json_decode(json_encode($total_data), true);
        $total = $this->totalData($total_data, 0, $app_code);
        if (!empty($order) && in_array($order, $sort_arr)) {
            $last_names = array_column($total_data, $order);
            array_multisort($last_names, $sort == 2 ? SORT_DESC : SORT_ASC, $total_data);
        }
        //分页
        $count = count($total_data);
        $data = collect($total_data)->forPage(intval($page), intval($size))->values()->toArray();
        return $this->responseSuccess(array('list' => $data, 'total' => $total, 'count' => (int)$count, 'page' => (int)$page, 'limit' => (int)$size));
    }

    //巨量广告计划维度数据
    public function plan(RequestInterface $request)
    {
        $timeSection = $request->query('startTime');
        $start_date = strtotime(date('Y-m-d', time()));
        $end_date = strtotime(date('Y-m-d', time())) + 60 * 60 * 24 - 1;
        if ($timeSection) {
            list($start_date, $end_date) = $timeSection;
            $start_date = strtotime(explode('T', $start_date)[0]);
            $end_date = strtotime(explode('T', $end_date)[0]) + 60 * 60 * 24 - 1;
        }
        $app_code = $request->query('appcode', 'wifi');
        $launch_type = $request->query('launch_type');  //1 关键行为 0 双出
        $plat = $request->query('plat');  //1 巨量 2 快手
        $ad_id = $request->query('ad_id');
        $advertiser_ids = $request->query('advertiser');
        $page = $request->query('page', 1);
        $size = $request->query('limit', 20);
        $sort = $request->query('sort');
        $order = $request->query('order');
        $sort_arr = ['install_pre_cost', 'active_pre_cost', 'install_roi', 'activate_roi', 'install_roi_24h', 'activate_roi_24h', 'install_withdraw', 'activate_withdraw', 'install_roi_withdraw', 'activate_roi_withdraw'];

        $order_sql = "";
        if (!empty($order) && !in_array($order, $sort_arr)) {
            if ($sort == 2) {
                $order_sql = ",a." . $order . " desc";
            } else {
                $order_sql = ",a." . $order . " asc";
            }
        }

        $where = " and a.app_code = '{$app_code}'";
        if ($plat == 2) {
            $where .= " and a.platform = 'ks'";
        } else if ($plat == 1) {
            $where .= " and a.platform = 'jl'";
        }
        if ($launch_type == 1) {
            $where .= ' and b.launch_type =1';
        } elseif ($launch_type == 2) {
            $where .= ' and b.launch_type =0';
        }
        if (!empty($advertiser_ids)) {
            $ad_str = '';
            foreach ($advertiser_ids as $ad) {
                $ad_str .= "'{$ad}',";
            }
            $ad_str = rtrim($ad_str, ',');
            $where .= " and a.advertiser_id in ({$ad_str})";
        }
        if (!empty($ad_id)) {
            $adp_str = '';
            foreach ($ad_id as $ad) {
                $adp_str .= "'" . $ad . "',";
            }
            $adp_str = rtrim($adp_str, ',');
            $where .= " and a.plan_id in ({$adp_str})";
        }
        $sql = "select a.*,b.advertiser_name,c.*,d.plan_name as ad_name
from report_daily_plan a 
left join mkt_advertiser b on a.advertiser_id = b.advertiser_id
left join mkt_plan d on a.plan_id = d.plan_id
left join report_daily_plan_ext c on a.id = c.id left join mkt_plan as d on a.plan_id = d.plan_id
        where UNIX_TIMESTAMP(a.date)  >= {$start_date}
        and   UNIX_TIMESTAMP(a.date)  < {$end_date}
        $where
        order by UNIX_TIMESTAMP(a.date)  desc
        $order_sql
        ";
        //计算数据和
        $total_data = DB::select($sql);
        $total_data = json_decode(json_encode($total_data), true);
        $total = $this->totalData($total_data, 0, $app_code);
        if (!empty($order) && in_array($order, $sort_arr)) {
            $last_names = array_column($total_data, $order);
            array_multisort($last_names, $sort == 2 ? SORT_DESC : SORT_ASC, $total_data);
        }
        //分页
        $count = count($total_data);
        $data = collect($total_data)->forPage(intval($page), intval($size))->values()->toArray();
        return $this->responseSuccess(array('list' => $data, 'total' => $total, 'count' => (int)$count, 'page' => (int)$page, 'limit' => (int)$size));
    }

    public function adNew(RequestInterface $request)
    {
        $timeSlot = $request->query('startTime');
        if (empty($timeSlot)) {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
        } else {
            $start_date = $timeSlot[0];
            $end_date = $timeSlot[1];
        }
        $app_code = $request->query('appcode', '');
        $launch_type = $request->query('launch_type');  //1 关键行为 0 双出
        $plat = $request->query('plat');  //1 巨量 2 快手
        $advertiser_ids = $request->query('advertiser');
        $page = $request->query('page', 1);
        $size = $request->query('limit', 20);
        $sort = $request->query('sort');
        $order = $request->query('order');
        $sort_arr = ['cost', 'withdraw', 'install_roi', 'install_roi_withdraw', 'install_roi_24h', 'activate_roi_24h',
            'install_revenue', 'install_num', 'activate_num', 'active', 'install_pre_cost', 'install_withdraw',
            'active_pre_cost', 'activate_withdraw', 'active_num',];

        $where = " and a.app_code = '{$app_code}'";
        if ($plat == 2) {
            $where .= " and a.platform = 'ks'";
        } else if ($plat == 1) {
            $where .= " and a.platform = 'jl'";
        }
        if ($launch_type == 1) {
            $where .= ' and b.launch_type =1';
        } elseif ($launch_type == 2) {
            $where .= ' and b.launch_type =0';
        }
        if (!empty($advertiser_ids)) {
            $ad_str = '';
            foreach ($advertiser_ids as $ad) {
                $ad_str .= "'{$ad}',";
            }
            $ad_str = rtrim($ad_str, ',');
            $where .= " and a.advertiser_id in ({$ad_str})";
        }
        $sql = "select 
                c.*,
                a.*,
                b.advertiser_name,
                c.install_payment,
                `c`.`activate_keep`->>'$.keep1.keep_num' as keep1_num,
                `c`.`activate_keep`->>'$.keep3.keep_num' as keep3_num,
                `c`.`activate_keep`->>'$.keep7.keep_num' as keep7_num,
                `c`.`activate_keep`->>'$.keep10.keep_num' as keep10_num,
                `c`.`activate_keep`->>'$.keep1.ago_num' as ago1_num,
                `c`.`activate_keep`->>'$.keep3.ago_num' as ago3_num,
                `c`.`activate_keep`->>'$.keep7.ago_num' as ago7_num,
                `c`.`activate_keep`->>'$.keep10.ago_num' as ago10_num,
                `c`.`recovery`->>'$.recovery3.revenue' as revenue3,
                `c`.`recovery`->>'$.recovery7.revenue' as revenue7,
                `c`.`recovery`->>'$.recovery10.revenue' as revenue10,
                `c`.`recovery`->>'$.recovery3.cost' as cost3,
                `c`.`recovery`->>'$.recovery7.cost' as cost7,
                `c`.`recovery`->>'$.recovery10.cost' as cost10,
                `c`.`activate_keep`->>'$.keep1.keep_rate' as keep1_rate,
                `c`.`activate_keep`->>'$.keep3.keep_rate' as keep3_rate,
                `c`.`activate_keep`->>'$.keep7.keep_rate' as keep7_rate,
                `c`.`activate_keep`->>'$.keep10.keep_rate' as keep10_rate,
                `c`.`recovery`->>'$.recovery3.recovery_rate' as recovery3_rate,
                `c`.`recovery`->>'$.recovery7.recovery_rate' as recovery7_rate,
                `c`.`recovery`->>'$.recovery10.recovery_rate' as recovery10_rate
            from report_daily_advertiser a join mkt_advertiser b on a.advertiser_id = b.advertiser_id
            left join report_daily_advertiser_ext c on a.id = c.id
            where a.date  >= '{$start_date}'
            and   a.date  <= '{$end_date}'
            $where order by a.date  desc ";
        //计算数据和
        $total_data = DB::select($sql);
        $total_data = json_decode(json_encode($total_data), true);
        $total = $this->totalData($total_data, 0, $app_code);
        if (!empty($order) && in_array($order, $sort_arr)) {
            $last_names = array_column($total_data, $order);
            array_multisort($last_names, $sort == 1 ? SORT_ASC : SORT_DESC, $total_data);
        }
        //分页
        $count = count($total_data);
        $data = array_slice($total_data, ($page - 1) * $size, $size);
        $data = $this->calData($data, 0, $app_code);
        return $this->responseSuccess(array('list' => $data, 'total' => $total, 'count' => (int)$count, 'page' => (int)$page, 'limit' => (int)$size));
    }

    public function planNew(RequestInterface $request)
    {
        $timeSlot = $request->query('startTime');
        if (empty($timeSlot)) {
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
        } else {
            $start_date = $timeSlot[0];
            $end_date = $timeSlot[1];
        }
        $app_code = $request->query('appcode', '');
        $status = $request->query('status');
        $launch_type = $request->query('launch_type');  //1 关键行为 0 双出
        $plat = $request->query('plat');  //1 巨量 2 快手
        $plan_ids = $request->query('ad_id');
        $advertiser_ids = $request->query('advertiser');
        $page = $request->query('page', 1);
        $size = $request->query('limit', 20);
        $sort = $request->query('sort');
        $order = $request->query('order');
        $sort_arr = ['cost', 'withdraw', 'install_roi', 'install_roi_withdraw', 'install_roi_24h', 'activate_roi_24h',
            'install_revenue', 'install_num', 'activate_num', 'active', 'install_pre_cost', 'install_withdraw',
            'active_pre_cost', 'activate_withdraw', 'active_num',];

        $where = " and a.app_code = '{$app_code}'";
        if ($plat == 2) {
            $where .= " and b.platform = 'ks'";
        } else if ($plat == 1) {
            $where .= " and b.platform = 'jl'";
        }
        if ($launch_type == 1) {
            $where .= ' and b.launch_type =1';
        } elseif ($launch_type == 2) {
            $where .= ' and b.launch_type =0';
        }
        if (!empty($advertiser_ids)) {
            $ad_str = '';
            foreach ($advertiser_ids as $ad) {
                $ad_str .= "'{$ad}',";
            }
            $ad_str = rtrim($ad_str, ',');
            $where .= " and a.advertiser_id in ({$ad_str})";
        }
        if (!empty($plan_ids)) {
            $adp_str = '';
            foreach ($plan_ids as $ad) {
                $adp_str .= "'" . $ad . "',";
            }
            $adp_str = rtrim($adp_str, ',');
            $where .= " and a.plan_id in ({$adp_str})";
        }

        $sql = "select c.*,a.*,
                b.advertiser_name,
                d.schedule_start,
                d.schedule_end,
                d.put_status as plan_status,
                d.plan_name as ad_name,
                d.plan_id as ad_id,
                c.install_payment,
                `c`.`activate_keep`->>'$.keep1.keep_num' as keep1_num,
                `c`.`activate_keep`->>'$.keep3.keep_num' as keep3_num,
                `c`.`activate_keep`->>'$.keep7.keep_num' as keep7_num,
                `c`.`activate_keep`->>'$.keep10.keep_num' as keep10_num,
                `c`.`activate_keep`->>'$.keep1.ago_num' as ago1_num,
                `c`.`activate_keep`->>'$.keep3.ago_num' as ago3_num,
                `c`.`activate_keep`->>'$.keep7.ago_num' as ago7_num,
                `c`.`activate_keep`->>'$.keep10.ago_num' as ago10_num,
                `c`.`recovery`->>'$.recovery3.revenue' as revenue3,
                `c`.`recovery`->>'$.recovery7.revenue' as revenue7,
                `c`.`recovery`->>'$.recovery10.revenue' as revenue10,
                `c`.`recovery`->>'$.recovery3.cost' as cost3,
                `c`.`recovery`->>'$.recovery7.cost' as cost7,
                `c`.`recovery`->>'$.recovery10.cost' as cost10,
                `c`.`activate_keep`->>'$.keep1.keep_rate' as keep1_rate,
                `c`.`activate_keep`->>'$.keep3.keep_rate' as keep3_rate,
                `c`.`activate_keep`->>'$.keep7.keep_rate' as keep7_rate,
                `c`.`activate_keep`->>'$.keep10.keep_rate' as keep10_rate,
                `c`.`recovery`->>'$.recovery3.recovery_rate' as recovery3_rate,
                `c`.`recovery`->>'$.recovery7.recovery_rate' as recovery7_rate,
                `c`.`recovery`->>'$.recovery10.recovery_rate' as recovery10_rate
            from report_daily_plan a 
            left join mkt_advertiser b on a.advertiser_id = b.advertiser_id
            left join mkt_plan d on a.plan_id = d.plan_id
            left join report_daily_plan_ext c on a.id = c.id
            where a.date  >= '{$start_date}'
            and   a.date  <= '{$end_date}'
            $where order by a.date  desc";

        //计算数据和
        $total_data = DB::select($sql);
        $total_data = json_decode(json_encode($total_data), true);
        $total = $this->totalData($total_data, 0, $app_code);
        if (!empty($order) && in_array($order, $sort_arr)) {
            $last_names = array_column($total_data, $order);
            array_multisort($last_names, $sort == 1 ? SORT_ASC : SORT_DESC, $total_data);
        }
        //分页
        $count = count($total_data);
        $data = array_slice($total_data, ($page - 1) * $size, $size);
        $data = $this->calData($data, 0, $app_code);
        return $this->responseSuccess(array('list' => $data, 'total' => $total, 'count' => (int)$count, 'page' => (int)$page, 'limit' => (int)$size));
    }

    //计算数据和
    protected function totalData($data, $single_price, $app_code = '')
    {
        $total['withdraw_total'] = round(array_sum(array_column($data, 'withdraw')), 2);
        $total['install_payment_total'] = round(array_sum(array_column($data, 'install_payment')), 2);
        //关键行为数
        $total['key_action_num_total'] = array_sum(array_column($data, 'key_action'));
        //激活数据
        $total['active_total'] = array_sum(array_column($data, 'active'));
        //展现数
        $total['show_total'] = array_sum(array_column($data, 'show'));
        //点击数
        $total['click_total'] = array_sum(array_column($data, 'click'));
        $total['click_total'] = round($total['click_total'], 2);
        //消耗
        $total['cost_total'] = array_sum(array_column($data, 'cost'));
        $total['cost_total'] = round($total['cost_total'], 2);

        //新增安装ROI install_roi  新增安装预估收益/消耗
        $total['install_revenue_total'] = array_sum(array_column($data, 'install_revenue'));
        $total['install_roi_total'] = $total['cost_total'] <= 0 ? 0 : $total['install_revenue_total'] / $total['cost_total'];
        $total['install_roi_total'] = round($total['install_roi_total'], 3);

        //关键行为率
        $total['key_action_rate_total'] = $total['active_total'] <= 0 ? 0 : $total['key_action_num_total'] * 100 / $total['active_total'];
        $total['key_action_rate_total'] = round($total['key_action_rate_total'], 3);

        //关键行为成本 =消耗/关键行为数
        $total['key_action_cost_total'] = $total['key_action_num_total'] <= 0 ? 0 : $total['cost_total'] / $total['key_action_num_total'];
        $total['key_action_cost_total'] = round($total['key_action_cost_total'], 3);
        //新增安装ROI install_roi(提现)  新增安装预估收益/消耗
        $total['install_revenue_total'] = array_sum(array_column($data, 'install_revenue'));
        $total['install_roi_total_withdraw'] = $total['cost_total'] <= 0 ? 0 : $total['install_revenue_total'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['install_roi_total_withdraw'] = round($total['install_roi_total_withdraw'], 3);

        //新增安装24hROI install_revenue24  新增安装24h预估收益/消耗
        $total['install_revenue_total24'] = array_sum(array_column($data, 'install_revenue24'));
        $total['install_roi_total24'] = $total['cost_total'] <= 0 ? 0 : $total['install_revenue_total24'] / $total['cost_total'];
        $total['install_roi_total24'] = round($total['install_roi_total24'], 3);

        //新增安装24hROI install_revenue24(提现)  新增安装24h预估收益/消耗
        $total['install_revenue_total24'] = array_sum(array_column($data, 'install_revenue24'));
        $total['install_roi_total24_withdraw'] = ($total['cost_total'] + $total['withdraw_total']) <= 0 ? 0 : $total['install_revenue_total24'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['install_roi_total24_withdraw'] = round($total['install_roi_total24_withdraw'], 3);


        //新增激活ROI activate_roi  新增激活预估收益/消耗
        $total['activate_revenue_total'] = array_sum(array_column($data, 'activate_revenue'));
        $total['activate_roi_total'] = $total['cost_total'] <= 0 ? 0 : $total['activate_revenue_total'] / $total['cost_total'];
        $total['activate_roi_total'] = round($total['activate_roi_total'], 3);

        //新增激活ROI activate_roi(提现)  新增激活预估收益/消耗
        $total['activate_revenue_total'] = array_sum(array_column($data, 'activate_revenue'));
        $total['activate_roi_total_withdraw'] = ($total['cost_total'] + $total['withdraw_total']) <= 0 ? 0 : $total['activate_revenue_total'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['activate_roi_total_withdraw'] = round($total['activate_roi_total_withdraw'], 3);

        //新增激活24ROI activate_roi24  新增激活预估收益/消耗
        $total['activate_revenue_total24'] = array_sum(array_column($data, 'activate_revenue24'));
        $total['activate_roi_total24'] = $total['cost_total'] <= 0 ? 0 : $total['activate_revenue_total24'] / $total['cost_total'];
        $total['activate_roi_total24'] = round($total['activate_roi_total24'], 3);

        //新增激活24ROI(提现) activate_roi24  新增激活预估收益/消耗
        $total['activate_revenue_total24'] = array_sum(array_column($data, 'activate_revenue24'));
        $total['activate_roi_total24_withdraw'] = ($total['cost_total'] + $total['withdraw_total']) <= 0 ? 0 : $total['activate_revenue_total24'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['activate_roi_total24_withdraw'] = round($total['activate_roi_total24_withdraw'], 3);

        //新增安装预估收益
        $total['install_revenue_total'] = array_sum(array_column($data, 'install_revenue'));
        $total['install_revenue_total'] = round($total['install_revenue_total'], 2);

        //新增安装ARPU
        $total['install_avg_revenue_sum'] = array_sum(array_column($data, 'install_revenue'));
        $total['install_num_total'] = array_sum(array_column($data, 'install_num'));
        $total['install_avg_revenue_total'] = $total['install_num_total'] <= 0 ? 0 : $total['install_avg_revenue_sum'] / $total['install_num_total'];
        $total['install_avg_revenue_total'] = round($total['install_avg_revenue_total'], 5);

        //新增安装24h安装roi
        $total['install_revenue24_sum'] = array_sum(array_column($data, 'install_revenue24'));
        $val['install_roi_24h_total'] = $total['cost_total'] <= 0 ? 0 : $total['install_revenue24_sum'] / $total['cost_total'];
        $val['install_roi_24h_total'] = round($val['install_roi_24h_total'], 3);

        //新增安装人均展示 install_show
        $total['install_show_total'] = array_sum(array_column($data, 'install_show'));
        $total['install_num_total'] = array_sum(array_column($data, 'install_num'));
        $total['install_avg_show_total'] = $total['install_num_total'] <= 0 ? 0 : $total['install_show_total'] / $total['install_num_total'];
        $total['install_avg_show_total'] = round($total['install_avg_show_total'], 2);

        //新增激活广告人均展示  新增激活广告展示次数/新增激活 activate_show
        $total['activate_show_total'] = array_sum(array_column($data, 'activate_show'));
        $total['activate_num_total'] = array_sum(array_column($data, 'activate_num'));
        $total['activate_avg_show_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['activate_show_total'] / $total['activate_num_total'];
        $total['activate_avg_show_total'] = round($total['activate_avg_show_total'], 2);

        //新增安装数
        $total['install_num_total'] = array_sum(array_column($data, 'install_num'));
        $total['install_num_total'] = round($total['install_num_total'], 2);

        //新增激活数 activate_num
        $total['activate_num_total'] = array_sum(array_column($data, 'activate_num'));
        $total['activate_num_total'] = round($total['activate_num_total'], 2);

        //新增激活七日留存数  activate_seven_day
        $total['activate_seven_day_total'] = array_sum(array_column($data, 'activate_seven_day'));
        $total['activate_seven_day_total'] = round($total['activate_seven_day_total'], 2);

        //DEU active_num
        $total['active_num_total'] = array_sum(array_column($data, 'active_num'));
        $total['active_num_total'] = round($total['active_num_total'], 2);

        //新增安装广告请求数 install_request
        $total['install_request_total'] = array_sum(array_column($data, 'install_request'));
        $total['install_request_total'] = round($total['install_request_total'], 2);

        //新增安装广告填充数 install_fill
        $total['install_fill_total'] = array_sum(array_column($data, 'install_fill'));
        $total['install_fill_total'] = round($total['install_fill_total'], 2);

        //新增安装广告展示数
        $total['install_show_total'] = array_sum(array_column($data, 'install_show'));
        $total['install_show_total'] = round($total['install_show_total'], 2);

        //新增安装广告点击数
        $total['install_click_total'] = array_sum(array_column($data, 'install_click'));
        $total['install_click_total'] = round($total['install_click_total'], 2);

        //DEU广告请求数
        $total['active_request_total'] = array_sum(array_column($data, 'active_request'));
        $total['active_request_total'] = round($total['active_request_total'], 2);

        //DEU广告填充数
        $total['active_fill_total'] = array_sum(array_column($data, 'active_fill'));
        $total['active_fill_total'] = round($total['active_fill_total'], 2);

        //DEU广告展示数
        $total['active_show_total'] = array_sum(array_column($data, 'active_show'));
        $total['active_show_total'] = round($total['active_show_total'], 2);


        //DEU广告点击数
        $total['active_click_total'] = array_sum(array_column($data, 'active_click'));
        $total['active_click_total'] = round($total['active_click_total'], 2);

        //百度锁屏展示数
        $total['active_lock_show_total'] = array_sum(array_column($data, 'active_lock_show'));
        $total['active_lock_show_total'] = round($total['active_lock_show_total'], 2);

        //active_lock_revenue 锁屏总收入
//        $total['active_lock_revenue_total'] = array_sum(array_column($data, 'active_lock_revenue'));

        //ecpm_roi_total 计算
//        $total['ecpm_roi_total'] = $total['cost_total'] <= 0 ? 0 : round((($single_price * $total['install_show_total'] / 1000) + $total['active_lock_revenue_total']) / $total['cost_total'], 3);

        $total['activate_num_total'] = array_sum(array_column($data, 'activate_num'));
        $total['activate_revenue_total'] = array_sum(array_column($data, 'activate_revenue'));
        $total['active_revenue_total'] = array_sum(array_column($data, 'active_revenue'));
        //新增激活ARPU 计算
        $total['new_active_arpu_total'] = $total['activate_num_total'] <= 0 ? '0' : $total['activate_revenue_total'] / $total['activate_num_total'];
        $total['new_active_arpu_total'] = round($total['new_active_arpu_total'], 2);

        $total['old_num_total'] = $total['active_num_total'] - $total['install_num_total'];
        //老用户ARPU 计算
        $total['old_arpu_total'] = $total['old_num_total'] <= 0 ? 0 : ($total['active_revenue_total'] - $total['activate_revenue_total']) / $total['old_num_total'];
        $total['old_arpu_total'] = round($total['old_arpu_total'], 2);

        //老用户广告人均展示 计算
        //$total['old_per_show_total'] = $total['old_num_total'] <= 0 ? 0 : ($total['active_show_total'] - $total['install_show_total']) / $total['old_num_total'];
        //$total['old_per_show_total'] = round($total['old_per_show_total'], 2);
        $app_code_map = ['kyvideo', 'pssvideo'];
        if (in_array($app_code, $app_code_map)) {
            $total['old_num_total'] = array_sum(array_column($data, 'old_user_num'));
            $total['old_show_total'] = array_sum(array_column($data, 'old_show'));
            $total['old_per_show_total'] = $total['old_num_total'] <= 0 ? '0' : $total['old_show_total'] / $total['old_num_total'];
        } else {
            $total['old_num_total'] = $total['active_num_total'] - $total['install_num_total'];
            $total['old_per_show_total'] = $total['old_num_total'] <= 0 ? '0' : ($total['active_show_total'] - $total['install_show_total']) / $total['old_num_total'];
        }


        $total['old_per_show_total'] = round($total['old_per_show_total'], 2);

        //新增安装成本 计算
        $total['install_pre_cost_total'] = $total['install_num_total'] <= 0 ? 0 : $total['cost_total'] / $total['install_num_total'];
        $total['install_pre_cost_total'] = round($total['install_pre_cost_total'], 2);

        //新增激活成本 计算 1
        $total['active_pre_cost_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['cost_total'] / $total['activate_num_total'];
        $total['active_pre_cost_total'] = round($total['active_pre_cost_total'], 2);

        //激活成本 =消耗/激活回传
        $total['active_cost_total'] = $total['active_total'] <= 0 ? 0 : round($total['cost_total'] / $total['active_total'], 2);


        //回/激占比  计算 1
        $total['return_active_rate_total'] = $total['activate_num_total'] <= 0 ? 0 : ($total['active_total'] / $total['activate_num_total']) * 100;
        $total['return_active_rate_total'] = round($total['return_active_rate_total'], 2);

        //新增安装次留率 计算  install_next_day / install_num
        $total['install_next_day_total'] = array_sum(array_column($data, 'install_next_day'));
        $total['install_next_day_rate_total'] = $total['install_num_total'] <= 0 ? 0 : $total['install_next_day_total'] / $total['install_num_total'];
        $total['install_next_day_rate_total'] = round($total['install_next_day_rate_total'], 2);

        //新增激活次留率 计算 activate_next_day / activate_num  1
        $total['activate_next_day_total'] = array_sum(array_column($data, 'activate_next_day'));
        $total['activate_num_total'] = array_sum(array_column($data, 'activate_num'));
        $total['activate_next_day_rate_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['activate_next_day_total'] / $total['activate_num_total'];
        $total['activate_next_day_rate_total'] = round($total['activate_next_day_rate_total'], 2);

        //激活回传次留率 计算 active / acitvate_num
        $total['activate_return_retention_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['active_total'] / $total['activate_num_total'];
        $total['activate_return_retention_total'] = round($total['activate_return_retention_total'], 2);

        //新增激活七日留存率 计算 activate_seven_day / acitvate_num
        $total['activate_seven_day_rate_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['activate_seven_day_total'] / $total['activate_num_total'];
        $total['activate_seven_day_rate_total'] = round($total['activate_seven_day_rate_total'], 2);


        //新增安装广告填充率 计算 install_fill/install_request
        $total['install_fill_rate_total'] = $total['install_request_total'] <= 0 ? 0 : $total['install_fill_total'] / $total['install_request_total'];
        $total['install_fill_rate_total'] = round($total['install_fill_rate_total'], 2);


        //新增安装广告展示率 计算 install_show/install_fill
        $total['install_show_rate_total'] = $total['install_fill_total'] <= 0 ? 0 : $total['install_show_total'] / $total['install_fill_total'];
        $total['install_show_rate_total'] = round($total['install_show_rate_total'], 2);

        //DEU广告填充率  active_fill/active_request
        $total['active_fill_rate_total'] = $total['active_request_total'] <= 0 ? 0 : $total['active_fill_total'] / $total['active_request_total'];
        $total['active_fill_rate_total'] = round($total['active_fill_rate_total'], 2);

        //DEU广告展示率 active_show/active_fill
        $total['active_show_rate_total'] = $total['active_fill_total'] <= 0 ? 0 : $total['active_show_total'] / $total['active_fill_total'];
        $total['active_show_rate_total'] = round($total['active_show_rate_total'], 2);

        //激活/安装GAP
        $total['activate_install_gap_total'] = $total['install_num_total'] == 0 ? 0 : ($total['activate_num_total'] / $total['install_num_total'] - 1) * 100;
        $total['activate_install_gap_total'] = round($total['activate_install_gap_total'], 2);


        //新增安装成本（提现） （消耗+提现）/安装数
        $total['install_withdraw_total'] = $total['install_num_total'] == 0 ? '0' : ($total['cost_total'] + $total['withdraw_total']) / $total['install_num_total'];
        $total['install_withdraw_total'] = round($total['install_withdraw_total'], 2);

        //新增激活成本（提现） （消耗+提现）/激活数
        $total['activate_withdraw_total'] = $total['activate_num_total'] == 0 ? '0' : ($total['cost_total'] + $total['withdraw_total']) / $total['activate_num_total'];
        $total['activate_withdraw_total'] = round($total['activate_withdraw_total'], 2);

        //新增安装roi 提现
        $total['install_roi_withdraw_total'] = ($total['cost_total'] + $total['withdraw_total']) <= 0 ? 0 : $total['install_revenue_total'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['install_roi_withdraw_total'] = round($total['install_roi_withdraw_total'], 3);

        //新增安装充值ROI
        $total['install_roi_payment_total'] = $total['cost_total'] <= 0 ? 0 : round(array_sum(array_column($data, 'install_payment')) / $total['cost_total'], 3);

        //新增激活roi 提现
        $total['activate_roi_withdraw_total'] = ($total['cost_total'] + $total['withdraw_total']) <= 0 ? 0 : $total['activate_revenue_total'] / ($total['cost_total'] + $total['withdraw_total']);
        $total['activate_roi_withdraw_total'] = round($total['activate_roi_withdraw_total'], 3);

        //新增安装广告人均展示(体内)
        $total['install_show_in_num_total'] = array_sum(array_column($data, 'install_show_in'));
        $total['install_show_in_total'] = $total['install_num_total'] <= 0 ? 0 : $total['install_show_in_num_total'] / $total['install_num_total'];
        $total['install_show_in_total'] = round($total['install_show_in_total'], 2);
        //新增安装广告人均展示(体外)
        $total['install_show_out_num_total'] = array_sum(array_column($data, 'install_show_out'));
        $total['install_show_out_total'] = $total['install_num_total'] <= 0 ? 0 : $total['install_show_out_num_total'] / $total['install_num_total'];
        $total['install_show_out_total'] = round($total['install_show_out_total'], 2);

        //新增激活广告人均展示(体内)
        $total['activate_show_in_num_total'] = array_sum(array_column($data, 'activate_show_in'));
        $total['activate_show_in_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['activate_show_in_num_total'] / $total['activate_num_total'];
        $total['activate_show_in_total'] = round($total['activate_show_in_total'], 2);
        //新增激活广告人均展示(体外)
        $total['activate_show_out_num_total'] = array_sum(array_column($data, 'activate_show_out'));
        $total['activate_show_out_total'] = $total['activate_num_total'] <= 0 ? 0 : $total['activate_show_out_num_total'] / $total['activate_num_total'];
        $total['activate_show_out_total'] = round($total['activate_show_out_total'], 2);

        //老用户广告人均展示(体内)
        $total['old_show_in_num_total'] = array_sum(array_column($data, 'old_show_in'));
        $total['old_show_in_total'] = $total['old_num_total'] <= 0 ? 0 : $total['old_show_in_num_total'] / $total['old_num_total'];
        $total['old_show_in_total'] = round($total['old_show_in_total'], 2);
        //老用户广告人均展示(体外）
        $total['old_show_out_num_total'] = array_sum(array_column($data, 'old_show_out'));
        $total['old_show_out_total'] = $total['old_num_total'] <= 0 ? 0 : $total['old_show_out_num_total'] / $total['old_num_total'];
        $total['old_show_out_total'] = round($total['old_show_out_total'], 2);
        //3、7、10日回本率
        $total['revenue3_total'] = array_sum(array_column($data, 'revenue3'));
        $total['revenue7_total'] = array_sum(array_column($data, 'revenue7'));
        $total['revenue10_total'] = array_sum(array_column($data, 'revenue10'));
        $total['cost3_total'] = array_sum(array_column($data, 'cost3'));
        $total['cost7_total'] = array_sum(array_column($data, 'cost7'));
        $total['cost10_total'] = array_sum(array_column($data, 'cost10'));
        $total['recovery3_rate_total'] = $total['cost3_total'] <= 0 ? 0 : round($total['revenue3_total'] / $total['cost3_total'] * 100, 2) . '%';
        $total['recovery7_rate_total'] = $total['cost7_total'] <= 0 ? 0 : round($total['revenue7_total'] / $total['cost7_total'] * 100, 2) . '%';
        $total['recovery10_rate_total'] = $total['cost10_total'] <= 0 ? 0 : round($total['revenue10_total'] / $total['cost10_total'] * 100, 2) . '%';
        //1、3、7、10日激活回传率
        $total['keep1_num_total'] = array_sum(array_column($data, 'keep1_num'));
        $total['keep3_num_total'] = array_sum(array_column($data, 'keep3_num'));
        $total['keep7_num_total'] = array_sum(array_column($data, 'keep7_num'));
        $total['keep10_num_total'] = array_sum(array_column($data, 'keep10_num'));
        $total['ago1_num_total'] = array_sum(array_column($data, 'ago1_num'));
        $total['ago3_num_total'] = array_sum(array_column($data, 'ago3_num'));
        $total['ago7_num_total'] = array_sum(array_column($data, 'ago7_num'));
        $total['ago10_num_total'] = array_sum(array_column($data, 'ago10_num'));
        $total['keep1_rate_total'] = $total['ago1_num_total'] <= 0 ? 0 : round($total['keep1_num_total'] / $total['ago1_num_total'] * 100, 2) . '%';
        $total['keep3_rate_total'] = $total['ago3_num_total'] <= 0 ? 0 : round($total['keep3_num_total'] / $total['ago3_num_total'] * 100, 2) . '%';
        $total['keep7_rate_total'] = $total['ago7_num_total'] <= 0 ? 0 : round($total['keep7_num_total'] / $total['ago7_num_total'] * 100, 2) . '%';
        $total['keep10_rate_total'] = $total['ago10_num_total'] <= 0 ? 0 : round($total['keep10_num_total'] / $total['ago10_num_total'] * 100, 2) . '%';
        return $total;
    }

    //计算数据
    protected function calData($data, $single_price, $app_code = '')
    {
        foreach ($data as $key => &$val) {
            $val['withdraw'] = is_null($val['withdraw']) ? 0 : $val['withdraw'];
            $val['install_payment'] = is_null($val['install_payment']) ? 0 : $val['install_payment'];
            $val['install_avg_show_in'] = is_null($val['install_avg_show_in']) ? 0 : $val['install_avg_show_in'];
            $val['install_avg_show_out'] = is_null($val['install_avg_show_out']) ? 0 : $val['install_avg_show_out'];
            $val['activate_avg_show_in'] = is_null($val['activate_avg_show_in']) ? 0 : $val['activate_avg_show_in'];
            $val['activate_avg_show_out'] = is_null($val['activate_avg_show_out']) ? 0 : $val['activate_avg_show_out'];
            $val['old_avg_show_in'] = is_null($val['old_avg_show_in']) ? 0 : $val['old_avg_show_in'];
            $val['old_avg_show_out'] = is_null($val['old_avg_show_out']) ? 0 : $val['old_avg_show_out'];
            $val['advertiser_id'] = $val['advertiser_id'] . ' ';
            //老用户 = DEU用户数 - 新增安装用户数
            //老用户广告人均展示=（DEU广告展示数 - 新增激活的广告展示数）/ 老用户
            $app_code_map = ['kyvideo', 'pssvideo'];
            if (in_array($app_code, $app_code_map)) {
                $val['old_num'] = $val['old_user_num'];
                $val['old_per_show'] = $val['old_num'] <= 0 ? '0' : $val['old_show'] / $val['old_num'];
            } else {
                $val['old_num'] = $val['active_num'] - $val['install_num'];
                $val['old_per_show'] = $val['old_num'] <= 0 ? '0' : ($val['active_show'] - $val['install_show']) / $val['old_num'];
            }
            $val['old_per_show'] = round($val['old_per_show'], 2);
            //新增激活广告人均展示
            $val['activate_avg_show'] = $val['activate_num'] <= 0 ? 0 : $val['activate_show'] / $val['activate_num'];
            $val['activate_avg_show'] = round($val['activate_avg_show'], 2);
            //新增激活ARPU = 新增激活用户带来的收入 / 新增激活的用户数
            $val['new_active_arpu'] = $val['activate_num'] == 0 ? '0' : $val['activate_revenue'] / $val['activate_num'];
            $val['new_active_arpu'] = round($val['new_active_arpu'], 2);

            //新增安装成本=消耗/新增安装数
            $val['install_pre_cost'] = $val['install_num'] == 0 ? '0' : $val['cost'] / $val['install_num'];
            $val['install_pre_cost'] = round($val['install_pre_cost'], 2);

//            //新增激活成本=消耗/新增激活数
//            $val['active_pre_cost'] = $val['activate_num'] == 0 ? '0' : $val['cost'] / $val['activate_num'];
//            $val['active_pre_cost'] = round($val['active_pre_cost'], 2);

            $val['active_pre_cost'] = round($val['active_cost'], 2);


            //激活回传次留率 = 巨量次留数/巨量激活数
//            $val['activate_return_retention'] = $val['active'] == 0 ? 0 : $val['attribution_next_day_open_cnt'] / $val['active'];
//            $val['activate_return_retention'] = round($val['activate_return_retention'], 2);
            $val['activate_return_retention'] = $val['activate_num'] <= 0 ? 0 : $val['active'] / $val['activate_num'];
            $val['activate_return_retention'] = round($val['activate_return_retention'], 2);

            //新增激活回传数 / 新增激活数 active/active_num 回/激占比
            $val['return_active_rate'] = $val['activate_num'] == 0 ? 0 : ($val['active'] / $val['activate_num']) * 100;
            $val['return_active_rate'] = round($val['return_active_rate'], 2);


            //老用户ARPU =（总收入active_revenue - 新增激活用户带来的收入activate_revenue/老用户
            $val['old_arpu'] = $val['old_num'] <= 0 ? 0 : ($val['active_revenue'] - $val['activate_revenue']) / $val['old_num'];
            $val['old_arpu'] = round($val['old_arpu'], 2);

            //(ecpm * 新增安装展现 /1000) + 锁屏收入 install_show
//            $val['ecpm_roi'] = $val['cost'] <= 0 ? 0 : round((($single_price * $val['install_show'] / 1000) + $val['active_lock_revenue']) / $val['cost'], 3);

            //安装收入/消耗 install_revenue / cost 新增安装roi
            $val['install_roi'] = $val['cost'] <= 0 ? 0 : $val['install_revenue'] / $val['cost'];
            $val['install_roi'] = round($val['install_roi'], 3);

            //激活收入/消耗 activate_revenue / cost 新增安装roi
            $val['activate_roi'] = $val['cost'] <= 0 ? 0 : $val['activate_revenue'] / $val['cost'];
            $val['activate_roi'] = round($val['activate_roi'], 3);

            //DEU广告展示率
            $val['active_show_rate'] = $val['active_request'] <= 0 ? 0 : $val['active_show'] / $val['active_request'];
            $val['active_show_rate'] = round($val['active_show_rate'], 2);

            //DEU广告填充率 active_fill_rate
            $val['active_fill_rate'] = $val['active_request'] <= 0 ? 0 : $val['active_fill'] / $val['active_request'];
            $val['active_fill_rate'] = round($val['active_fill_rate'], 2);

            //新增安装广告展示率 install_show_rate
            $val['install_show_rate'] = $val['install_fill'] <= 0 ? 0 : $val['install_show'] / $val['install_fill'];
            $val['install_show_rate'] = round($val['install_show_rate'], 2);

            //新增安装广告填充率 install_fill_rate
            $val['install_fill_rate'] = $val['install_request'] <= 0 ? 0 : $val['install_fill'] / $val['install_request'];
            $val['install_fill_rate'] = round($val['install_fill_rate'], 2);

            //激活/安装GAP
            $val['activate_install_gap'] = $val['install_num'] == 0 ? 0 : ($val['activate_num'] / $val['install_num'] - 1) * 100;
            $val['activate_install_gap'] = round($val['activate_install_gap'], 2);

            //新增安装次留率
            $val['install_next_day_rate'] = $val['install_num'] <= 0 ? 0 : $val['install_next_day'] / $val['install_num'];
            $val['install_next_day_rate'] = round($val['install_next_day_rate'], 2);

            // 新增激活次留率
            $val['activate_next_day_rate'] = $val['activate_num'] <= 0 ? 0 : $val['activate_next_day'] / $val['activate_num'];
            $val['activate_next_day_rate'] = round($val['activate_next_day_rate'], 2);

            //新增安装广告人均展示
            $val['install_avg_show'] = $val['install_num'] <= 0 ? 0 : $val['install_show'] / $val['install_num'];
            $val['install_avg_show'] = round($val['install_avg_show'], 2);

            $val['create_date'] = $val['create_time'] <= 0 ? '-' : date('Y-m-d H:i', $val['create_time']);
            $val['update_date'] = $val['update_time'] <= 0 ? '-' : date('Y-m-d H:i', $val['update_time']);
            //分时段roi
            if (empty($val['hour_revenue']) || empty($val['hour_cost'])) {
                $val['hour_roi'] = '-';
            } else {
                $h_cost = empty($val['hour_cost']) ? [] : json_decode($val['hour_cost'], true);
                $h_revenue = empty($val['hour_revenue']) ? [] : json_decode($val['hour_revenue'], true);
                if (!empty($h_cost) && !empty($h_revenue)) {
                    $hour_arr = [];
                    foreach ($h_cost as $h => $h_val) {
                        $h = date('Y-m-d H:i:s', strtotime($h));
                        if (!isset($h_revenue[$h])) {
                            $hour_arr[$h] = 0;
                        } else {
                            $hour_arr[$h]['roi'] = (isset($h_val['cost']) && $h_val['cost'] > 0) ? (round($h_revenue[$h] / $h_val['cost'], 2)) : 0;
                            $hour_arr[$h]['cost'] = isset($h_val['cost']) ? round($h_val['cost'], 2) : 0;
                            $hour_arr[$h]['convert_cost'] = isset($h_val['convert_cost']) ? round($h_val['convert_cost'], 2) : 0;

                        }
                    }
                }
                $val['hour_roi'] = !empty($hour_arr) ? json_encode($hour_arr) : '-';
            }

            if (empty($val['hour_revenue']) || empty($val['hour_cost'])) {
                $val['hour_arr_withdraw'] = '-';
            } else {
                $h_cost = empty($val['hour_cost']) ? [] : json_decode($val['hour_cost'], true);
                $h_revenue = empty($val['hour_revenue']) ? [] : json_decode($val['hour_revenue'], true);
                $hour_withdraw = empty($val['hour_withdraw']) ? [] : json_decode($val['hour_withdraw'], true);
                if (!empty($h_cost) && !empty($h_revenue)) {
                    $hour_arr_withdraw = [];
                    foreach ($h_cost as $h => $h_val) {
                        $h = date('Y-m-d H:i:s', strtotime($h));
                        if (!isset($h_revenue[$h])) {
                            $hour_arr_withdraw[$h] = 0;
                        } else {
                            if (!empty($hour_withdraw)) {
                                foreach ($hour_withdraw as $wi) {
                                    if (isset($wi[$h])) {
                                        $h_val['cost'] = $h_val['cost'] + $wi[$h];
                                    } else {
                                        $h_val['cost'] = $h_val['cost'] + 0;
                                    }
                                }
                            }
                            $hour_arr_withdraw[$h]['roi'] = (isset($h_val['cost']) && $h_val['cost'] > 0) ? (round($h_revenue[$h] / $h_val['cost'], 2)) : 0;
                            $hour_arr_withdraw[$h]['cost'] = isset($h_val['cost']) ? round($h_val['cost'], 2) : 0;
                            $hour_arr_withdraw[$h]['convert_cost'] = isset($h_val['convert_cost']) ? round($h_val['convert_cost'], 2) : 0;

                        }
                    }
                }
                $val['hour_roi_withdraw'] = !empty($hour_arr_withdraw) ? json_encode($hour_arr_withdraw) : '-';
            }
            //新增安装24h安装roi
            $val['install_roi_24h'] = $val['cost'] <= 0 ? 0 : $val['install_revenue24'] / $val['cost'];
            $val['install_roi_24h'] = round($val['install_roi_24h'], 3);

            //新增激活24hroi
            $val['activate_roi_24h'] = ($val['cost'] + $val['withdraw']) <= 0 ? 0 : $val['activate_revenue24'] / ($val['cost'] + $val['withdraw']);
            $val['activate_roi_24h'] = round($val['activate_roi_24h'], 3);

            //新增安装24h安装roi（提现）
            $val['install_roi_24h_withdraw'] = ($val['cost'] + $val['withdraw']) <= 0 ? 0 : $val['install_revenue24'] / ($val['cost'] + $val['withdraw']);
            $val['install_roi_24h_withdraw'] = round($val['install_roi_24h_withdraw'], 3);

            //新增激活24hroi(提现）
            $val['activate_roi_24h_withdraw'] = ($val['cost'] + $val['withdraw']) <= 0 ? 0 : $val['activate_revenue24'] / ($val['cost'] + $val['withdraw']);
            $val['activate_roi_24h_withdraw'] = round($val['activate_roi_24h_withdraw'], 3);

            //新增安装成本（提现） （消耗+提现）/安装数
            $val['install_withdraw'] = $val['install_num'] == 0 ? 0 : ($val['cost'] + $val['withdraw']) / $val['install_num'];
            $val['install_withdraw'] = round($val['install_withdraw'], 2);

            //新增激活成本（提现） （消耗+提现）/激活数
            $val['activate_withdraw'] = $val['activate_num'] == 0 ? 0 : ($val['cost'] + $val['withdraw']) / $val['activate_num'];
            $val['activate_withdraw'] = round($val['activate_withdraw'], 2);

            //安装收入/消耗 install_revenue / cost 新增安装roi 提现
            $val['install_roi_withdraw'] = ($val['cost'] + $val['withdraw']) <= 0 ? 0 : $val['install_revenue'] / ($val['cost'] + $val['withdraw']);
            $val['install_roi_withdraw'] = round($val['install_roi_withdraw'], 3);

            //激活收入/消耗 activate_revenue / cost 新增安装roi 提现
            $val['activate_roi_withdraw'] = ($val['cost'] + $val['withdraw']) <= 0 ? 0 : $val['activate_revenue'] / ($val['cost'] + $val['withdraw']);
            $val['activate_roi_withdraw'] = round($val['activate_roi_withdraw'], 3);

            // 新增安装充值RIO
            $val['install_rio_payment'] = $val['cost'] <= 0 ? 0 : round(($val['install_payment'] ?? 0) / $val['cost'], 3);

        }

        return $data;
    }

    //修改广告主维度标记状态
    public function changeStatus(RequestInterface $request)
    {
        return $this->responseSuccess(['state' => '修改成功']);
        $id = $request->input('id');
        $status = $request->input('status');
        $data['status'] = $status;
        $res = DB::connection('tools')->table('advertiser_data_new')->where('id', $id)->update($data);
        if ($res) {
            return $this->responseSuccess(['state' => '修改成功']);
        } else {
            return $this->responseError(-1, '修改失败');
        }
    }

    //修改广告主维度标记状态
    public function changePlanStatus(RequestInterface $request)
    {
        return $this->responseSuccess(['state' => '修改成功']);
        $id = $request->input('id');
        $status = $request->input('status');
        $data['status'] = $status;
        $res = DB::connection('tools')->table('advertiser_plan_data_new')->where('id', $id)->update($data);
        if ($res) {
            return $this->responseSuccess(['state' => '修改成功']);
        } else {
            return $this->responseError(-1, '修改失败');
        }
    }


    /**
     * 激活留存
     * @param RequestInterface $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function longKeep(RequestInterface $request)
    {
        $timeSlot = $request->query('startTime');
        if (empty($timeSlot)) {
            $start_date = $end_date = date('Y-m-d', time());
        } else {
            $start_date = date('Y-m-d', strtotime(explode('T', $timeSlot[0])[0]));
            $end_date = date('Y-m-d', strtotime(explode('T', $timeSlot[1])[0]) + 86400 - 1);
        }
        $advertiser_id = $request->query('advertiser');
        $app_code = $request->query('appcode', 'wifi');
        $source = $request->query('plat');
        $launch_type = $request->query('launch_type');
        $query = DB::table('report_daily_advertiser as a')
            ->join('mkt_advertiser as b', 'a.advertiser_id', '=', 'b.advertiser_id')
            ->leftJoin('report_daily_advertiser_ext as rdad', 'a.id', '=', 'rdad.id')
            ->where(function ($query) use ($app_code) {
                if (!empty($app_code)) {
                    $query->where('a.app_code', $app_code);
                }
            })
            ->where(function ($query) use ($advertiser_id) {
                if (!empty($advertiser_id)) {
                    $query->whereIn('a.advertiser_id', $advertiser_id);
                }
            })
            ->where(function ($query) use ($launch_type) {
                if (!empty($launch_type)) {
                    if ($launch_type == 2) {
                        $launch_type = 0;
                    }
                    $query->where('b.launch_type', $launch_type);
                }
            })
            ->where(function ($query) use ($source) {
                if (!empty($source)) {
                    if ($source == 1) {
                        $query->where('b.platform', "jl");
                    } else {
                        $query->where('b.platform', "ks");
                    }
                } else {
                    $query->whereBetween('b.platform', ["jl", "ks"]);
                }
            })
            ->whereBetween('a.date', [$start_date, $end_date]);
        $list_data = $query
            ->select([
                'a.id',
                'a.app_code',
                'a.advertiser_id',
                'b.advertiser_name',
                'a.date',
                'rdad.install_num',
                'rdad.activate_num',
                'a.active',
                DB::raw("`rdad`.`install_keep` ->> '$.keep1.keep_rate' AS keep1_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep2.keep_rate' AS keep2_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep3.keep_rate' AS keep3_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep4.keep_rate' AS keep4_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep5.keep_rate' AS keep5_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep6.keep_rate' AS keep6_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep7.keep_rate' AS keep7_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery0.recovery_rate' AS recovery0_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery1.recovery_rate' AS recovery1_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery2.recovery_rate' AS recovery2_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery3.recovery_rate' AS recovery3_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery4.recovery_rate' AS recovery4_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery5.recovery_rate' AS recovery5_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery6.recovery_rate' AS recovery6_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery7.recovery_rate' AS recovery7_rate ")
            ])
            ->orderBy('rdad.install_num', 'desc')
            ->get();
        $total_data = $query->select([
            'a.id',
            'a.app_code',
            'a.advertiser_id',
            'a.date',
            'b.advertiser_name',
            DB::raw("sum(rdad.install_num) as total_install_num"),
            DB::raw("sum(rdad.activate_num) as total_activate_num"),
            DB::raw("sum(a.active) as total_active"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep1.keep_num') / sum(`rdad`.`install_keep`->'$.keep1.ago_num')) as keep1_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep2.keep_num') / sum(`rdad`.`install_keep`->'$.keep2.ago_num')) as keep2_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep3.keep_num') / sum(`rdad`.`install_keep`->'$.keep3.ago_num')) as keep3_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep4.keep_num') / sum(`rdad`.`install_keep`->'$.keep4.ago_num')) as keep4_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep5.keep_num') / sum(`rdad`.`install_keep`->'$.keep5.ago_num')) as keep5_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep6.keep_num') / sum(`rdad`.`install_keep`->'$.keep6.ago_num')) as keep6_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep7.keep_num') / sum(`rdad`.`install_keep`->'$.keep7.ago_num')) as keep7_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery0.revenue') / sum(`rdad`.`recovery`->'$.recovery0.cost')) as recovery0_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery1.revenue') / sum(`rdad`.`recovery`->'$.recovery1.cost')) as recovery1_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery2.revenue') / sum(`rdad`.`recovery`->'$.recovery2.cost')) as recovery2_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery3.revenue') / sum(`rdad`.`recovery`->'$.recovery3.cost')) as recovery3_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery4.revenue') / sum(`rdad`.`recovery`->'$.recovery4.cost')) as recovery4_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery5.revenue') / sum(`rdad`.`recovery`->'$.recovery5.cost')) as recovery5_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery6.revenue') / sum(`rdad`.`recovery`->'$.recovery6.cost')) as recovery6_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery7.revenue') / sum(`rdad`.`recovery`->'$.recovery7.cost')) as recovery7_rate"),
        ])->first();

        $total_data = json_decode(json_encode($total_data), true);
        $total_data['keep1_rate'] = 100 * round($total_data['keep1_rate'], 4) . '%';
        $total_data['keep2_rate'] = 100 * round($total_data['keep2_rate'], 4) . '%';
        $total_data['keep3_rate'] = 100 * round($total_data['keep3_rate'], 4) . '%';
        $total_data['keep4_rate'] = 100 * round($total_data['keep4_rate'], 4) . '%';
        $total_data['keep5_rate'] = 100 * round($total_data['keep5_rate'], 4) . '%';
        $total_data['keep6_rate'] = 100 * round($total_data['keep6_rate'], 4) . '%';
        $total_data['keep7_rate'] = 100 * round($total_data['keep7_rate'], 4) . '%';
        $total_data['recovery0_rate'] = 100 * round($total_data['recovery0_rate'], 4) . '%';
        $total_data['recovery1_rate'] = 100 * round($total_data['recovery1_rate'], 4) . '%';
        $total_data['recovery2_rate'] = 100 * round($total_data['recovery2_rate'], 4) . '%';
        $total_data['recovery3_rate'] = 100 * round($total_data['recovery3_rate'], 4) . '%';
        $total_data['recovery4_rate'] = 100 * round($total_data['recovery4_rate'], 4) . '%';
        $total_data['recovery5_rate'] = 100 * round($total_data['recovery5_rate'], 4) . '%';
        $total_data['recovery6_rate'] = 100 * round($total_data['recovery6_rate'], 4) . '%';
        $total_data['recovery7_rate'] = 100 * round($total_data['recovery7_rate'], 4) . '%';
        $list_data = json_decode(json_encode($list_data), true);
        return $this->responseSuccess(array('list' => $list_data, 'total' => $total_data));
    }

    /**
     * 长留存-广告计划
     */
    public function longPlan(RequestInterface $request)
    {
        $timeSlot = $request->query('startTime');
        if (empty($timeSlot)) {
            $start_date = $end_date = date('Y-m-d', time());
        } else {
            $start_date = date('Y-m-d', strtotime(explode('T', $timeSlot[0])[0]));
            $end_date = date('Y-m-d', strtotime(explode('T', $timeSlot[1])[0]) + 86400 - 1);
        }
        $advertiser_id = $request->query('advertiser');
        $app_code = $request->query('appcode', 'wifi');
        $source = $request->query('plat');
        $launch_type = $request->query('launch_type');
        $query = DB::table('report_daily_plan as a')
            ->leftjoin('mkt_advertiser as b', 'a.advertiser_id', '=', 'b.advertiser_id')
            ->leftjoin('report_daily_plan_ext as rdad', 'a.id', '=', 'rdad.id')
            ->leftjoin('mkt_plan as mp', 'mp.plan_id', '=', 'a.plan_id')
            ->where(function ($query) use ($app_code) {
                if (!empty($app_code)) {
                    $query->where('a.app_code', $app_code);
                }
            })
            ->where(function ($query) use ($advertiser_id) {
                if (!empty($advertiser_id)) {
                    $query->whereIn('a.advertiser_id', $advertiser_id);
                }
            })
            ->where(function ($query) use ($launch_type) {
                if (!empty($launch_type)) {
                    if ($launch_type == 2) {
                        $launch_type = 0;
                    }
                    $query->where('b.launch_type', $launch_type);
                }
            })
            ->where(function ($query) use ($source) {
                if (!empty($source)) {
                    if ($source == 1) {
                        $query->where('b.platform', "jl");
                    } else {
                        $query->where('b.platform', "ks");
                    }
                } else {
                    $query->whereBetween('b.platform', ["jl", "ks"]);
                }
            })
            ->whereBetween('a.date', [$start_date, $end_date]);
        $list_data = $query
            ->select([
                'a.id',
                'a.app_code',
                'a.advertiser_id',
                'b.advertiser_name',
                'a.plan_id as ad_id',
                'mp.plan_name as ad_name',
                'a.date',
                'rdad.install_num',
                'rdad.activate_num',
                'a.active',
                DB::raw("`rdad`.`install_keep` ->> '$.keep1.keep_rate' AS keep1_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep2.keep_rate' AS keep2_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep3.keep_rate' AS keep3_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep4.keep_rate' AS keep4_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep5.keep_rate' AS keep5_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep6.keep_rate' AS keep6_rate"),
                DB::raw("`rdad`.`install_keep` ->> '$.keep7.keep_rate' AS keep7_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery0.recovery_rate' AS recovery0_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery1.recovery_rate' AS recovery1_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery2.recovery_rate' AS recovery2_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery3.recovery_rate' AS recovery3_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery4.recovery_rate' AS recovery4_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery5.recovery_rate' AS recovery5_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery6.recovery_rate' AS recovery6_rate"),
                DB::raw("`rdad`.`recovery` ->> '$.recovery7.recovery_rate' AS recovery7_rate ")
            ])
            ->orderBy('rdad.install_num', 'desc')->get();
        $total_data = $query->select([
            'a.id',
            'a.app_code',
            'a.advertiser_id',
            'a.date',
            'b.advertiser_name',
            'a.plan_id as ad_id',
            'mp.plan_name as ad_name',
            DB::raw("sum(rdad.install_num) as total_install_num"),
            DB::raw("sum(rdad.activate_num) as total_activate_num"),
            DB::raw("sum(a.active) as total_active"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep1.keep_num') / sum(`rdad`.`install_keep`->'$.keep1.ago_num')) as keep1_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep2.keep_num') / sum(`rdad`.`install_keep`->'$.keep2.ago_num')) as keep2_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep3.keep_num') / sum(`rdad`.`install_keep`->'$.keep3.ago_num')) as keep3_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep4.keep_num') / sum(`rdad`.`install_keep`->'$.keep4.ago_num')) as keep4_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep5.keep_num') / sum(`rdad`.`install_keep`->'$.keep5.ago_num')) as keep5_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep6.keep_num') / sum(`rdad`.`install_keep`->'$.keep6.ago_num')) as keep6_rate"),
            DB::raw("(sum(`rdad`.`install_keep`->'$.keep7.keep_num') / sum(`rdad`.`install_keep`->'$.keep7.ago_num')) as keep7_rate"),

            DB::raw("(sum(`rdad`.`recovery`->'$.recovery0.revenue') / sum(`rdad`.`recovery`->'$.recovery0.cost')) as recovery0_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery1.revenue') / sum(`rdad`.`recovery`->'$.recovery1.cost')) as recovery1_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery2.revenue') / sum(`rdad`.`recovery`->'$.recovery2.cost')) as recovery2_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery3.revenue') / sum(`rdad`.`recovery`->'$.recovery3.cost')) as recovery3_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery4.revenue') / sum(`rdad`.`recovery`->'$.recovery4.cost')) as recovery4_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery5.revenue') / sum(`rdad`.`recovery`->'$.recovery5.cost')) as recovery5_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery6.revenue') / sum(`rdad`.`recovery`->'$.recovery6.cost')) as recovery6_rate"),
            DB::raw("(sum(`rdad`.`recovery`->'$.recovery7.revenue') / sum(`rdad`.`recovery`->'$.recovery7.cost')) as recovery7_rate"),
        ])->first();

        $total_data = json_decode(json_encode($total_data), true);
        $total_data['keep1_rate'] = 100 * round($total_data['keep1_rate'], 4) . '%';
        $total_data['keep2_rate'] = 100 * round($total_data['keep2_rate'], 4) . '%';
        $total_data['keep3_rate'] = 100 * round($total_data['keep3_rate'], 4) . '%';
        $total_data['keep4_rate'] = 100 * round($total_data['keep4_rate'], 4) . '%';
        $total_data['keep5_rate'] = 100 * round($total_data['keep5_rate'], 4) . '%';
        $total_data['keep6_rate'] = 100 * round($total_data['keep6_rate'], 4) . '%';
        $total_data['keep7_rate'] = 100 * round($total_data['keep7_rate'], 4) . '%';
        $total_data['recovery0_rate'] = 100 * round($total_data['recovery0_rate'], 4) . '%';
        $total_data['recovery1_rate'] = 100 * round($total_data['recovery1_rate'], 4) . '%';
        $total_data['recovery2_rate'] = 100 * round($total_data['recovery2_rate'], 4) . '%';
        $total_data['recovery3_rate'] = 100 * round($total_data['recovery3_rate'], 4) . '%';
        $total_data['recovery4_rate'] = 100 * round($total_data['recovery4_rate'], 4) . '%';
        $total_data['recovery5_rate'] = 100 * round($total_data['recovery5_rate'], 4) . '%';
        $total_data['recovery6_rate'] = 100 * round($total_data['recovery6_rate'], 4) . '%';
        $total_data['recovery7_rate'] = 100 * round($total_data['recovery7_rate'], 4) . '%';
        $list_data = json_decode(json_encode($list_data), true);
        return $this->responseSuccess(array('list' => $list_data, 'total' => $total_data));
    }
}
