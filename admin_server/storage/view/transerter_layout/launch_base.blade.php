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
        td{color: #484848;min-width: 100px;}
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <th style="color: #F00">{{ $data['appName'] }}</th>
        <th colspan="9">投放基础数据</th>
        <th colspan="12">产品基础数据</th>
    </tr>
    <tr>
        <td>日期</td>

        <td>当日消耗</td>
        <td>当日激活</td>
        <td>激活成本</td>
        <td>当日转化</td>
        <td>转化成本</td>
        <td>激活次留</td>
        <td>整体 ROI</td>
        <td>新户 ARPU</td>
        <td>新户 ECPM</td>

        <td>新增安装</td>
        <td>新增次留</td>
        <td>归因</td>
        <td>新增人均</td>
        <td>当日 DAU</td>
        <td>当日 DEU</td>
        <td>DEU 人均</td>
        <td>华为新增</td>
        <td>小米新增</td>
        <td>OPPO 新增</td>
        <td>VIVO 新增</td>
        <td>回收 GAP</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($data['records'] as $row)
        <tr>
            <td>{{ date('y/m/d', strtotime($row['created_date'])) }}</td>

            <td>{{ round($row['cost'], 2) }}</td>
            <td>{{ $row['active'] }}</td>
            <td>{{ round($row['active'] > 0 ?$row['cost']/$row['active']:0, 2) }}</td>
            <td>{{ $row['convert'] }}</td>
            <td>{{ round($row['convert'] > 0 ?$row['cost']/$row['convert']:0, 2) }}</td>
            <td>{{ round(($row['active'] > 0 ?$row['next_day_open'] / $row['active']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['cost'] > 0 ?$row['active_revenue'] / $row['cost']:0), 2) }}</td>
            <td>{{ round(($row['increment_install'] > 0 ?$row['imputed_revenue'] / $row['increment_install']:0), 2) }}</td>
            <td>{{ round(($row['increment_show'] > 0 ?$row['imputed_revenue'] / $row['increment_show']:0) * 1000, 2) }}</td>

            <td>{{ $row['increment_install'] }}</td>
            <td>{{ round(($row['increment_install']>0?$row['increment_next_day_open'] / $row['increment_install']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['version_install']>0?$row['version_guiyin'] / $row['version_install']:0) * 100, 2) }}%</td>
            <td>{{ round($row['increment_install']>0?$row['increment_show'] /$row['increment_install']:0, 2) }}</td>
            <td>{{ $row['dau'] }}</td>
            <td>{{ $row['deu'] }}</td>
            <td>{{ round($row['deu'] > 0 ? $row['show'] / $row['deu']:0, 2) }}</td>
            <td>{{ round(($row['increment_install']>0?$row['install_huawei'] / $row['increment_install']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['increment_install']>0?$row['install_xiaomi'] / $row['increment_install']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['increment_install']>0?$row['install_oppo'] / $row['increment_install']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['increment_install']>0?$row['install_vivo'] / $row['increment_install']:0) * 100, 2) }}%</td>
            <td>{{ round(($row['media_revenue']>0?($row['active_revenue']-$row['media_revenue']) / $row['media_revenue']:0) * 100, 2) }}%</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>