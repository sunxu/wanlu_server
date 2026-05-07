import request from '@/utils/request'

export function getList(params) {
  return request({
    url: '/uc/mis/inner/withdrawal/statement',
    method: 'get',
    params
  })
}

export function exportStatistics(params) {
  return request({
    url: '/uc/mis/inner/withdrawal/statement/export',
    method: 'get',
    params
  })
}
