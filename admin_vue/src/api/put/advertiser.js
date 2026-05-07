import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/put/advertiser/list',
    method: 'post',
    data: body
  })
}

export function bindApp(body) {
  return request({
    url: '/uc/v1/put/advertiser/bind-app',
    method: 'post',
    data: body
  })
}

export function saveData(body) {
  return request({
    url: '/uc/v1/put/advertiser/save',
    method: 'post',
    data: body
  })
}

export function updateStatus(body) {
  return request({
    url: '/uc/v1/put/advertiser/status',
    method: 'post',
    data: body
  })
}

export function getUrl(appCode, version = 'v1') {
  return request({
    url: `/uc/${version}/put/url/${appCode}`,
    method: 'post'
  })
}
