import request from '@/utils/request'

export function getList(params) {
  return request({
    url: '/uc/mis/inner/user/list',
    method: 'get',
    params
  })
}
