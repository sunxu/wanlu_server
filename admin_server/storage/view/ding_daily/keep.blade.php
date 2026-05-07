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
        <th colspan="15">次留率 - {{ $data['launch_type_name'] }}</th>
    </tr>
    <tr>
        <th colspan="3">全部</th>
        <th colspan="3">华为</th>
        <th colspan="3">小米</th>
        <th colspan="3">oppo</th>
        <th colspan="3">vivo</th>
    </tr>
    <tr>
        <td>日期</td>
        <td>安装数</td>
        <td>次留数</td>
        <td>次留率</td>
        <td>安装数</td>
        <td>次留数</td>
        <td>次留率</td>
        <td>安装数</td>
        <td>次留数</td>
        <td>次留率</td>
        <td>安装数</td>
        <td>次留数</td>
        <td>次留率</td>
        <td>安装数</td>
        <td>次留数</td>
        <td>次留率</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($data['list'] as $row)
    <tr>
        <td>{{ $row['date'] }}</td>

        <td>{{ $row['total_install'] }}</td>
        <td>{{ $row['total_keep'] }}</td>
        <td>{{ $row['total_keep_rate'] }}</td>

        <td>{{ $row['huawei_install'] }}</td>
        <td>{{ $row['huawei_keep'] }}</td>
        <td>{{ $row['huawei_keep_rate'] }}</td>

        <td>{{ $row['xiaomi_install'] }}</td>
        <td>{{ $row['xiaomi_keep'] }}</td>
        <td>{{ $row['xiaomi_keep_rate'] }}</td>

        <td>{{ $row['oppo_install'] }}</td>
        <td>{{ $row['oppo_keep'] }}</td>
        <td>{{ $row['oppo_keep_rate'] }}</td>

        <td>{{ $row['vivo_install'] }}</td>
        <td>{{ $row['vivo_keep'] }}</td>
        <td>{{ $row['vivo_keep_rate'] }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>