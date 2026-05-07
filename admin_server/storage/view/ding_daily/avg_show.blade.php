<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>日报</title>
    <style>
        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            background-color: #8c8c8c;
        }
        table
        {
            background: #FFF;
            border-collapse:collapse;
            width: 100%;
        }
        table,th, td
        {
            border: 1px solid black;
            text-align: center;
            padding: 4px 0;
        }
        th{color: #333333}
        td{ color: #484848}
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <th rowspan="2" style="color: #F00">{{ $data['app_name'] }}</th>
        <th colspan="19">人均展示 - {{ $data['launch_type_name'] }}</th>
    </tr>
    <tr>
        <th colspan="3">全部</th>
        <th colspan="4">华为</th>
        <th colspan="4">小米</th>
        <th colspan="4">oppo</th>
        <th colspan="4">vivo</th>
    </tr>
    <tr>
        <td>日期</td>
        <td>安装数</td>
        <td>展示数</td>
        <td>人均展示</td>
        <td>安装数</td>
        <td>展示数</td>
        <td>人均展示</td>
        <td>新增占比</td>
        <td>安装数</td>
        <td>展示数</td>
        <td>人均展示</td>
        <td>新增占比</td>
        <td>安装数</td>
        <td>展示数</td>
        <td>人均展示</td>
        <td>新增占比</td>
        <td>安装数</td>
        <td>展示数</td>
        <td>人均展示</td>
        <td>新增占比</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($data['list'] as $row)
    <tr>
        <td>{{ $row['date'] }}</td>
        <td>{{ $row['total_install'] }}</td>
        <td>{{ $row['total_show'] }}</td>
        <td>{{ $row['total_avg_show'] }}</td>

        <td>{{ $row['huawei_install'] }}</td>
        <td>{{ $row['huawei_show'] }}</td>
        <td>{{ $row['huawei_avg_show'] }}</td>
        <td>{{ $row['huawei_install_rate'] }}</td>

        <td>{{ $row['xiaomi_install'] }}</td>
        <td>{{ $row['xiaomi_show'] }}</td>
        <td>{{ $row['xiaomi_avg_show'] }}</td>
        <td>{{ $row['xiaomi_install_rate'] }}</td>

        <td>{{ $row['oppo_install'] }}</td>
        <td>{{ $row['oppo_show'] }}</td>
        <td>{{ $row['oppo_avg_show'] }}</td>
        <td>{{ $row['oppo_install_rate'] }}</td>

        <td>{{ $row['vivo_install'] }}</td>
        <td>{{ $row['vivo_show'] }}</td>
        <td>{{ $row['vivo_avg_show'] }}</td>
        <td>{{ $row['vivo_install_rate'] }}</td>

    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>