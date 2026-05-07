import request from '@/utils/request'

export function getList(params) {
  return request({
    url: '/uc/mis/inner/withdrawal/statistics/total',
    method: 'get',
    params
  })
}

export function exportStatistics(params) {
  return request({
    url: '/uc/mis/inner/withdrawal/statistics/total/export',
    method: 'get',
    params
  })
}

export function qrcode(merchantId, appId) {
  return request({
    url: `/uc/mis/inner/withdrawal/qrcode/${merchantId}/${appId}`,
    method: 'get'
  })
}
