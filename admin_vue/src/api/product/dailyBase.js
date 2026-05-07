import request from '@/utils/request'

export function dailyBase(params, version = 'v1') {
  return request({
    url: `/uc/${version}/product/dailyBase`,
    method: 'get',
    params
  })
}
