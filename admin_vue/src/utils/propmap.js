const tHeader = ['日期', '广告主 ID', '广告主名称', '消耗', '提现金额(当日)', '新增安装', '安装成本', '提现安装成本', '新增安装arpu', '新增安装roi', '(充值)新增安装roi', '(提现)新增安装roi', '24h新增安装roi', '(提现)24h新增安装roi', '激活成本', '关键行为成本', '达成率', '新增激活回传数', '关键行为数', '3日回本', '7日回本', '10日回本', '激活回传次留率', '激活回传3留率', '激活回传7留率', '激活回传10留率'];
const filterVal = ['date', 'advertiser_id', 'advertiser_name', 'cost', 'withdraw', 'install_num', 'install_pre_cost', 'install_withdraw', 'install_avg_revenue', 'install_roi', 'install_roi_payment', 'install_roi_withdraw', 'install_roi_24h', 'install_roi_24h_withdraw', 'active_cost', 'key_action_cost', 'key_action_rate', 'avtive', 'key_action', 'recovery3_rate', 'recovery7_rate', 'recovery10_rate', 'keep1_rate', 'keep3_rate', 'keep7_rate', 'keep10_rate'];
export const totalHeader = {
        "before_budget":"",
        "budget":"",
        "cost":"",
        "budget_finish":"",
        "install_num":"",
        "install_pre_cost":"",
        "install_withdraw":"",
        "install_avg_revenue":"",
        "install_roi":"",
        "install_roi_payment":"",
        "install_roi_withdraw":"",
        "install_roi_24h":"",
        "install_roi_24h_withdraw":"",
        "active_cost":"",
        "key_action_cost":"",
        "key_action_rate":"",
        "avtive":"",
        "key_action":"",
        "recovery3_rate":"",
        "recovery7_rate":"",
        "recovery10_rate":"",
        "keep1_rate":"",
        "keep3_rate":"",
        "keep7_rate":"",
        "keep10_rate":"",
        "record_time":""
      };




// 构建映射对象
export const mappingZH = {};
export const mappingEN = {};
tHeader.forEach((item, index) => {
    mappingZH[item] = filterVal[index];
});
filterVal.forEach((item, index) => {
    mappingEN[item] = tHeader[index];
});

// 根据汉字获取英文字段
export function getEnglishFromChinese(chinese) {
    return mappingZH[chinese] || null;
}

// 根据英文字段获取汉字
export function getChineseFromEnglish(english) {
    return mappingEN[english] || null;
}

// 把中文key转换为英文key
export function resetprop(list) {
    return list.map(item => ({
        ...item,
        ...item[mappingEN[item.日期]]
    }))
   
}

// 测试
// console.log(getEnglishFromChinese('新增安装')); // install_num
// console.log(getChineseFromEnglish('install_num')); // 新增安装
