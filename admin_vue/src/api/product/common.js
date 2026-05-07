import request from '@/utils/request'

export function getApp(body) {
  return request({
    url: '/uc/v1/product/app',
    method: 'post',
    data: body
  })
}

export function getAdvertiser(body) {
  return request({
    url: '/uc/v1/product/advertiser',
    method: 'post',
    data: body
  })
}
