//投放渠道|注意需要与策略查询处配置统一
const SOURCE_MAP = {
  jl: '巨量',
  ks: '快手',
  op : 'OPPO',
  hw : '华为',
  vi : 'vivo',
  bd : '百度',
  tx: '腾讯营销',
  honor: '荣耀'
}
//投放方式
const LAUNCH_TYPE_MAP = {
  0: '次留双出',
  1: '关键行为'
}
const LAUNCH_TYPE_MAP_ROI = {
  12: '关键行为',
  2: 'ROI',
  4: '次留双出',
}
//渠道
const CHANNEL_MAP = {
  1: '华为',
  2: '小米',
  3: 'oppo',
  4: 'vivo',
  5: '豌豆荚',
  6: '360',
  7: '应用宝',
  8: '2345',
  10: '巨量推广10',
  11: '广点通推广11',
  12: '百度推广12',
  13: '快手推广13',
  14: '荣耀推广14',
  100: '华为推广100',
  300: 'oppo推广300',
  400: 'vivo推广400',
  1001: '推广1001',
  1002: '推广1002'
}
//厂商
const VENDOR_MAP = {
  huawei: '华为',
  xiaomi: '小米',
  oppo: 'oppo',
  vivo: 'vivo',
  other: '其他'
}

//广告类型
const AD_TYPE_MAP = {
  native: '信息流',
  inter: '插屏',
  inter_full: '全屏插屏',
  full_video: '全屏视频',
  splash: '开屏',
  reword_video: '激励视频'
}

//广告平台
const AD_PLATFORM_MAP = {
  ks: '快手',
  csj: '穿山甲',
  ylh: '优量汇',
  hw: '华为',
  op: 'OPPO',
  vi: 'vivo',
  bd: '百度'
}

//广告策略类型
const AD_STRATEGY_KEY_MAP = {
  common: '正常',
  patrol: '审核',
  audit: '巡检',
  except: '异常设备'
}


export default {
  SOURCE_MAP,
  LAUNCH_TYPE_MAP,
  LAUNCH_TYPE_MAP_ROI,
  CHANNEL_MAP,
  VENDOR_MAP,
  AD_TYPE_MAP,
  AD_PLATFORM_MAP,
  AD_STRATEGY_KEY_MAP
}
