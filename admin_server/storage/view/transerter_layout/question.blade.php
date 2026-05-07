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
        <td>日期</td>
        <td>安装数</td>
        <td>归因数</td>
        <td>激活数</td>
        <td>注册数</td>
        <td>答题人数</td>
        <td>答题次数</td>
    </tr>
    </thead>
    <tbody>
    @foreach ($data['records'] as $row)
    <tr>
        <td>{{ $row['date'] }}</td>
        <td>{{ $row['install'] }}</td>
        <td>{{ $row['guiyin'] }}</td>
        <td>{{ $row['active'] }}</td>
        <td>{{ $row['register'] }}</td>
        <td>{{ $row['personTime'] }}</td>
        <td>{{ $row['questionTime'] }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>