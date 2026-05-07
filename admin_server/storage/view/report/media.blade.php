<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>报表</title>
    <style>
        body {
            font-family: Tahoma, Verdana, Arial, sans-serif;
            background-color: #8c8c8c;
        }

        table {
            background: #FFF;
            border-collapse: collapse;
            width: 100%;
        }

        table, th, td {
            border: 1px solid black;
            text-align: center;
            padding: 4px 0;
        }

        th {
            color: #333333
        }

        td {
            color: #484848;
            min-width: 100px;
        }
    </style>
</head>
<body>
<table>
    <thead>
    <tr>
        <th style="color: #F00">{{ $date }}</th>
        <th colspan="9">已更新媒体：{{ $media }}</th>
    </tr>
    <tr>
        <td>日期</td>
        <td>应用标识</td>
        <td>应用名称</td>
        <td>投放状态</td>
        <td>消耗</td>
        <td>真实回收</td>
        <td>真实ROI</td>
        <td>统计回收</td>
        <td>统计ROI</td>
        <td>回收GAP</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($list as $row)
        <tr>
            <td>{{ $row['created_date'] }}</td>
            <td>{{ $row['app_code'] }}</td>
            <td>{{ $row['app_name'] }}</td>
            <td>{{ $row['ad_status'] }}</td>
            <td>{{ $row['cost'] }}</td>
            <td>{{ $row['real_revenue'] }}</td>
            <td>{{ $row['real_roi'] }}</td>
            <td>{{ $row['revenue'] }}</td>
            <td>{{ $row['roi'] }}</td>
            <td>{{ $row['revenue_gap'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>