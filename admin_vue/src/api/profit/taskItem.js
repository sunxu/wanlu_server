import request from '@/utils/request'

export function getList(params) {
  return request({
    url: '/uc/mis/inner/task/statistics/item/day',
    method: 'get',
    params
  })
}
