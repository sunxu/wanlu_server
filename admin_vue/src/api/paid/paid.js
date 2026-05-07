import request from '@/utils/request'

export function reportDailyRechargeList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/report/daily/recharge`,
    method: 'get',
    params
  })
}
