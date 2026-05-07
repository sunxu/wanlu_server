import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/product/vendor',
    method: 'post',
    data: body
  })
}
