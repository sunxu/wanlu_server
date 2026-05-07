import request from '@/utils/request'

export function totalApp(params, version = 'v1') {
  return request({
    url: `/uc/${version}/revenue/app`,
    method: 'get',
    params
  })
}

export function totalCompany(params, version = 'v1') {
  return request({
    url: `/uc/${version}/revenue/company`,
    method: 'get',
    params
  })
}
