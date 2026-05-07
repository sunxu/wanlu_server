<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use Hyperf\HttpMessage\Stream\SwooleStream;

class HtmlController extends AbstractController
{

    public function ad_performance()
    {
        $code = $this->request->cookie('vue_admin_template_app_code');

        $list = Db::table("ad_performance")->where('app_code', $code)
            ->orderBy('id', 'desc')
            ->limit(24 * 7)
            ->get()->toArray();
        $list = json_decode(json_encode($list), true);

        return $this->responseSuccess(['list' => $list]);

//        $html = $this->print_list($list, $list[0]['app_name']);
//        return $this->response
//            ->withAddedHeader('Content-Type', 'text/html; charset=utf-8')
//            ->withBody(new SwooleStream($html));
    }

    public function report_advertiser()
    {
        $list = Db::table("report_advertiser")
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get()->toArray();
        $list = json_decode(json_encode($list), true);

        return $this->responseSuccess(['list' => $list]);

//        $html = $this->print_list($list, "report_advertiser");
//        return $this->response
//            ->withAddedHeader('Content-Type', 'text/html; charset=utf-8')
//            ->withBody(new SwooleStream($html));
    }

    private function print_list($list, $title)
    {
        ob_start();
        echo "<html><head><title>{$title}</title></head><body>";
        echo "<table border='1px solid green'><tr>";
        foreach ($list[0] as $key => $value) {
            echo "<th>{$key}</th>";
        }
        echo "</tr>";

        foreach ($list as $item) {
            echo "<tr>";
            foreach ($item as $value) {
                echo "<td>{$value}</td>";
            }
            echo "</tr>";
        }
        echo "</table></body></html>";
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
