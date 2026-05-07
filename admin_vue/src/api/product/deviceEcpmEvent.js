import request from '@/utils/request'

export function getCondition(appCode, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/product/deviceecpmevent/condition`,
    method: 'get'
  })
}

export function getList(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/product/deviceecpmevent`,
    method: 'get',
    params
  })
}
