<?php
/**
 * 通用助手类
 */
declare(strict_types=1);

namespace App\Services\Helper;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class Helper
{
    /**
     * 自定义返回
     * @param int $code
     * @param array|Collection $data
     * @param string $msg
     * @return array
     */
    public static function return(int $code = 0, $data = [], string $msg = ''): array
    {
        // 错误配置
        $config = config('error');
        // 错误信息
        $error = [
            'code' => $code,
            'msg' => empty($msg) ? (empty($config[$code]) ? 'failed' : $config[$code]) : $msg,
            'data' => empty($data) ? [] : $data
        ];
        // 返回
        return $error;
    }

    /**
     * 自定义响应|JSON
     * @param int $code
     * @param array|Collection $data
     * @param string $msg
     * @param array $header
     * @return JsonResponse
     */
    public static function response(int $code = 0, $data = [], string $msg = '', array $header = []): JsonResponse
    {
        // 错误配置
        $config = config('error');
        // 错误信息
        $error = [
            'code' => $code,
            'msg' => empty($msg) ? (empty($config[$code]) ? 'failed' : $config[$code]) : $msg,
            'data' => empty($data) ? (object)[] : $data
        ];
        // 默认头信息
        $default = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => '*',
            'Access-Control-Allow-Headers' => '*',
            'X-Host' => env('HOST_NAME')
        ];
        json_encode($error);
        // 合并头信息
        $header = array_merge($default, $header);
        // 状态码
        $http_code = ($code >= 100 && $code <= 600) ? $code : 200;
        // 返回|不转义中文(256)+不转义斜线(64)+数组强制转为对象(16)
        return response()->json($error, $http_code, $header, 320);
    }

    /**
     * 自定义请求
     * @param string $url
     * @param string $type
     * @param array|string $data
     * @param array $header
     * @return array
     */
    public static function request(string $url, string $type = 'GET', $data = '', array $header = []): array
    {
        // 当前时间
        $curr_time = microtime(true);
        // 初始化
        $handle = curl_init();
        // 请求方式
        if (strtoupper($type) === 'GET') {
            empty($data) || $url .= '?' . http_build_query($data);
        } else {
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        }
        // 通用配置
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handle, CURLOPT_HEADER, 0);
        // 头信息
        if (!empty($header)) {
            $keys = array_keys($header);
            // 关联数组
            if (array_keys($keys) !== $keys) {
                foreach ($header as $k => $v) {
                    $headerFormat[] = sprintf('%s:%s', $k, $v);
                }
            }
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headerFormat ?? $header);
        }
        // 执行请求
        $result = curl_exec($handle);
        // 状态码
        $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        // 错误信息
        $err_no = curl_errno($handle);
        // 关闭资源
        curl_close($handle);
        // 返回
        return [
            'err_no' => $err_no,
            'http_code' => $http_code,
            'used_time' => sprintf('%.0fms', (microtime(true) - $curr_time) * 1000),
            'result' => $result
        ];
    }

    /**
     * 数组转XML
     * @param array $param
     * @return string
     */
    public static function arrayToXml(array $param): string
    {
        // 初始化
        $xml = '';
        // 循环
        foreach ($param as $k => $v) {
            $xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
        }
        // 返回
        return sprintf('<xml>%s</xml>', $xml);
    }

    /**
     * XML转数组
     * @param string $xml
     * @return array
     */
    public static function xmlToArray(string $xml): array
    {
        // 禁止加载外部实体
        libxml_disable_entity_loader(true);
        // XML转对象
        try {
            $xml = simplexml_load_string($xml);
        } catch (Exception $e) {
            return [];
        }
        // 转数组
        $data = json_decode(json_encode($xml), true);
        // 返回
        return $data;
    }

    /**
     * 导出CSV
     * @param string $filename
     * @param array $data
     * @return Response
     */
    public static function exportCsv(string $filename, array $data): Response
    {
        // 头信息
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment;filename={$filename}.csv",
            'Cache-Control' => 'must-revalidate,post-check=0,pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public'
        ];
        // 内容
        $content = '';
        // 循环
        foreach ($data as $line) {
            // 拼接
            $content .= implode(',', $line) . "\n";
        }
        // 响应
        return response($content, 200, $headers);
    }

    /**
     * 生成唯一ID
     * @param int $length
     * @return string
     */
    public static function uniqueId(int $length = 20): string
    {
        // 20位
        list ($micro, $second) = explode(' ', microtime());
        $id = sprintf('%s%06d', date('YmdHis', intval($second)), substr($micro, 2, 6));
        // 24+
        if ($length >= 24) {
            $length = $length - 20;
            $id .= sprintf("%0{$length}d", mt_rand(1, 9999));
        }
        // 返回
        return $id;
    }

    /**
     * 手机号格式校验
     * @param string $data
     * @return bool
     */
    public static function isMobile(string $data): bool
    {
        $pattern = "/^1[3-9][0-9]{9}$/";
        if (preg_match($pattern, $data)) {
            return true;
        }
        return false;
    }

    /**
     * 身份证号格式校验
     * @param string $data
     * @return bool
     */
    public static function isIdCard(string $data): bool
    {
        $data = strtoupper($data);
        $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
        // 正则
        if (!preg_match($regx, $data)) {
            return false;
        }
        // 只能是18或者15位位
        if (strlen($data) != 18 && strlen($data) != 15) {
            return false;
        }
        // 15位转18位
        if (strlen($data) == 15) {
            if (array_search(substr($data, 12, 3), ['996', '997', '998', '999']) !== false) {
                $data = substr($data, 0, 6) . '18' . substr($data, 6, 15);
            } else {
                $data = substr($data, 0, 6) . '19' . substr($data, 6, 15);
            }
            $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
            $verify_number_list = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
            $checksum = 0;
            for ($i = 0; $i < strlen($data); $i++) {
                $checksum += substr($data, $i, 1) * $factor[$i];
            }
            $mod = $checksum % 11;
            $verify_number = $verify_number_list[$mod];
            $data = $data . $verify_number;
        }
        // 取出本体码
        $idcard_base = substr($data, 0, 17);
        // 取出校验码
        $verify_code = substr($data, 17, 1);
        // 加权因子
        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        // 校验码对应值
        $verify_code_list = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        // 根据前17位计算校验码
        $total = 0;
        for ($i = 0; $i < 17; $i++) {
            $total += substr($idcard_base, $i, 1) * $factor[$i];
        }
        // 取模
        $mod = $total % 11;
        // 比较校验码
        if ($verify_code == $verify_code_list[$mod]) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 生成树形结构
     * @param array $data
     * @param int $parent_id
     * @return array
     */
    public static function makeTree(array $data, int $parent_id = 0): array
    {
        // 初始化
        $result = [];
        // 循环
        foreach ($data as $v) {
            // 跳过
            if (!isset($v['id']) || !isset($v['parent_id']) || !isset($v['sort_order'])) {
                continue;
            }
            // 比对
            if ($v['parent_id'] == $parent_id) {
                // 递归
                $v['children'] = self::makeTree($data, $v['id']);
                // 赋值
                $result[$v['sort_order']] = $v;
            }
        }
        // 排序
        ksort($result);
        // 返回
        return $result;
    }

    /**
     * 版本大小比较
     * @param string $va 比较版本
     * @param string $vb 基准版本
     * @return int 大于0: va > vb, 小于0 va < vb, 等于0 va=vb
     */
    public static function compareVersion(string $va, string $vb)
    {
        //小版本号补前导零
        $arrVersionA = explode('.', $va);
        $formatVersionA = '';
        foreach ($arrVersionA as $v) {
            $formatVersionA .= str_pad($v, 4, '0', STR_PAD_LEFT);
        }

        //小版本号补前导零
        $arrVersionB = explode('.', $vb);
        $formatVersionB = '';
        foreach ($arrVersionB as $v) {
            $formatVersionB .= str_pad($v, 4, '0', STR_PAD_LEFT);
        }

        //去掉末尾的0
        $formatVersionA = rtrim($formatVersionA, '0');
        $formatVersionB = rtrim($formatVersionB, '0');

        return strcmp($formatVersionA, $formatVersionB);
    }


    /**
     * 二进制位存储留存天数
     *
     * @param int $day
     * @param int $bitNum
     * @return int
     */
    public static function getMoreKeep(int $day, int $bitNum): int
    {
        if (1 << $day & $bitNum) {
            return $day;
        }

        return 0;
    }

    /**
     * 二进制位存储留存天数
     *
     * @param int $day
     * @param int $bitNum
     * @return int
     */
    public static function setMoreKeep(int $day, int $bitNum = 0): int
    {
        $offset = 1 << $day;
        $bitNum = $bitNum | $offset;

        return $bitNum;
    }
}
