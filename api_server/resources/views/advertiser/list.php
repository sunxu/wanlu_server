<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title></title>
    <script src="https://www.layuicdn.com/auto/layui.js"></script>
</head>
<fieldset class="layui-elem-field layui-field-title" style="margin-top: 20px;">
    <legend>投放数据</legend>
</fieldset>
<form class="layui-form" action="" method="get" lay-filter="myForm">
    <div class="layui-form-item">
        <div class="layui-inline">
            <label class="layui-form-label">应用</label>
            <div class="layui-input-block">
                <select name="app" lay-verify="required">
                    <?php foreach ($appList as $appCode => $appName): ?>
                        <option value="<?php echo $appCode ?>"><?php echo $appName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="layui-inline">
            <label class="layui-form-label">日期范围</label>
            <div class="layui-inline" id="date-range">
                <div class="layui-input-inline">
                    <input type="text" required name="start" id="start" class="layui-input" placeholder="开始日期">
                </div>
                <div class="layui-form-mid">-</div>
                <div class="layui-input-inline">
                    <input type="text" required name="end" id="end" class="layui-input" placeholder="结束日期">
                </div>
                <div class="layui-input-inline">
                    <input type="text" name="export" value="1" hidden/>
                    <button type="submit" class="layui-btn">下载</button>
                </div>
            </div>
        </div>
    </div>
</form>
<body>
<script>
    layui.use(['layer', 'form', 'jquery', 'laydate'], function () {
        var layer = layui.layer,
            $ = layui.jquery,
            laydate = layui.laydate,
            form = layui.form;

        laydate.render({
            elem: '#date-range'
            , range: ['#start', '#end']
        });
    });

</script>
</body>
</html>
