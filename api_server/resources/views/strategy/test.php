<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title></title>
    <script src="https://www.layuicdn.com/auto/layui.js"></script>
</head>
<fieldset class="layui-elem-field layui-field-title" style="margin-top: 20px;">
    <legend><?php echo $appName;?>下放策略</legend>
</fieldset>
    <form class="layui-form" action="" method="post" lay-filter="myForm">
        <div class="layui-form-item">
            <label class="layui-form-label">选择版本</label>
            <div class="layui-input-inline">
                <select name="versionId" lay-filter="select" lay-verify="required">
                    <option></option>
                </select>
            </div>
        </div>
        <div class="layui-form-item">
            <label class="layui-form-label">版本号</label>
            <div class="layui-input-inline">
                <input type="text" name="version" required  lay-verify="required" placeholder="请输入标题" autocomplete="off" class="layui-input">
            </div>
        </div>
        <div class="layui-form-item" pane="">
            <label class="layui-form-label">测试策略</label>
            <div class="layui-input-block">
                <input type="radio" name="test" value="0" title="关闭">
                <input type="radio" name="test" value="1" title="审核">
                <input type="radio" name="test" value="2" title="正常">
                <input type="radio" name="test" value="3" title="巡检">
            </div>
        </div>
        
        <div class="layui-form-item">
            <div class="layui-input-block">
                <button type="submit" class="layui-btn" lay-submit="" lay-filter="save">保存</button>
                <button type="reset" class="layui-btn layui-btn-primary">重置</button>
            </div>
        </div>
    </form>
<body>
<script>
    layui.use(['layer', 'form','jquery'], function () {
        var layer = layui.layer,
            $ = layui.jquery,
            form = layui.form;
        var versionList = {};
        function info(){
            $.get('/strategy/info/<?php echo $appId;?>', function (response) {
                if(response.code == 0){
                    versionList = response.data.versionList;
                    var option = '<option></option>';
                    for(i=0;i<versionList.length;i++){
                        option += '<option value='+versionList[i]['id']+'>'+versionList[i]['version']+'</option>'
                    }
                    $("select[name='versionId']").html(option);
                    form.render('select')
                }
            });
        }
        info();

        form.on('select(select)', function(data){
            $("input[name='version']").val('');
            // $("input[name='test']").attr("checked",false)
            if(data.value != ''){
                for(var i=0;i<versionList.length;i++){
                    if(versionList[i]['id'] == data.value){
                        $("input[name='version']").val(versionList[i]['version']);
                        $("input:radio").eq(versionList[i]['test_status']).prop("checked",true);
                    }
                }
            }
            form.render('radio')
        });

        form.on('submit(save)', function(data){
            $.post('/strategy/save/<?php echo $appId;?>',data.field,function(response){
                if(response.code == 0){
                    layer.msg('保存成功',{
                        icon: 1,
                        time: 2000 //2秒关闭（如果不配置，默认是3秒）
                    },function(){
                        window.location.reload();
                    });
                }else{
                    layer.msg('保存失败');
                }
            });
            return false; //阻止表单跳转。如果需要表单跳转，去掉这段即可。
        });
    });

</script>
</body>
</html>
