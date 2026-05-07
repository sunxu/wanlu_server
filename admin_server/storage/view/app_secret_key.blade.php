<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>app</title>
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
<p><b>APP:</b>{{ $app_pro['app_name'] }}</p>
<p><b>线上</b></p>
<p><b>ID:</b>{{ $app_pro['id'] }}</p>
<p><b>KEY:</b>{{ $app_pro['key'] }}</p>
<p><b>APPID:</b>{{ $app_pro['appid'] }}</p>
<p><b>SECRET:</b>{{ $app_pro['secret'] }}</p>
<p><b>投放状态:</b>{{ $app_pro['ad_status'] }}</p>
<br>
<p><b>测试</b></p>
<p><b>ID:</b>{{ $app_test['id'] }}</p>
<p><b>KEY:</b>{{ $app_test['key'] }}</p>
<p><b>APPID:</b>{{ $app_test['appid'] }}</p>
<p><b>SECRET:</b>{{ $app_test['secret'] }}</p>
</body>
</html>