import request from '@/utils/request'

export function getList(data) {
  return request({
    url: '/uc/v1/product/keep',
    method: 'post',
    data
  })
}
