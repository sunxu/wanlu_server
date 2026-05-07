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
            padding: 4px 0;
            border: 1px solid black;
            text-align: center;
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
        <th colspan="13">心跳时长</th>
    </tr>
    <tr>
        <th rowspan="2">版本</th>
        <th colspan="3">华为</th>
        <th colspan="3">小米</th>
        <th colspan="3">oppo</th>
        <th colspan="3">vivo</th>
    </tr>
    <tr>
        <td>日期</td>
        <td>8.0以下</td>
        <td>8.0~10</td>
        <td>11及以上</td>
        <td>8.0以下</td>
        <td>8.0~10</td>
        <td>11及以上</td>
        <td>8.0以下</td>
        <td>8.0~10</td>
        <td>11及以上</td>
        <td>8.0以下</td>
        <td>8.0~10</td>
        <td>11及以上</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($data['list'] as $row)
    <tr>
        <td>{{ $row['date'] }}</td>
        <td>{{ $row['app_version'] }}</td>

        <td>{{ $row['huawei_lt8_rate'] }}</td>
        <td>{{ $row['huawei_ge8_lt11_rate'] }}</td>
        <td>{{ $row['huawei_ge11_rate'] }}</td>

        <td>{{ $row['xiaomi_lt8_rate'] }}</td>
        <td>{{ $row['xiaomi_ge8_lt11_rate'] }}</td>
        <td>{{ $row['xiaomi_ge11_rate'] }}</td>

        <td>{{ $row['oppo_lt8_rate'] }}</td>
        <td>{{ $row['oppo_ge8_lt11_rate'] }}</td>
        <td>{{ $row['oppo_ge11_rate'] }}</td>

        <td>{{ $row['vivo_lt8_rate'] }}</td>
        <td>{{ $row['vivo_ge8_lt11_rate'] }}</td>
        <td>{{ $row['vivo_ge11_rate'] }}</td>

    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>