import request from '@/utils/request'

export function Query(body) {
  return request({
    url: '/uc/v1/ad/ascribe/query',
    method: 'post',
    data: body
  })
}

export function Save(body) {
  return request({
    url: '/uc/v1/ad/ascribe/save',
    method: 'post',
    data: body
  })
}
