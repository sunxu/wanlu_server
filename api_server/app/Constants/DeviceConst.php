<?php

namespace App\Constants;

class DeviceConst
{
    /**
     * 设备激活状态
     */
    const ACTIVE_STATUS = [
        'off' => 0,//未激活
        'on' => 1,//激活未通知
        'on_notify' => 2//激活已通知
    ];
}