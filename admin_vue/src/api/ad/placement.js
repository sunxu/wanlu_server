import request from '@/utils/request'

export function getAccount(body) {
  return request({
    url: '/uc/v1/ad/placement/account-list',
    method: 'post',
    data: body
  })
}

export function getApp(body) {
  return request({
    url: '/uc/v1/ad/placement/app-list',
    method: 'post',
    data: body
  })
}

export function queryApp(body) {
  return request({
    url: '/uc/v1/ad/placement/app-query',
    method: 'post',
    data: body
  })
}

export function createPlacement(body) {
  return request({
    url: '/uc/v1/ad/placement/create-placement',
    method: 'post',
    data: body
  })
}

export function bindTopon(body) {
  return request({
    url: '/uc/v1/ad/placement/bind-topon',
    method: 'post',
    data: body
  })
}

export function upload(body) {
  return request({
    url: '/uc/v1/ad/placement/upload',
    method: 'post',
    data: body
  })
}

export function csjExport(body, version = 'v1') {
  return request({
    url: `/uc/${version}/placement/csj/export`,
    method: 'post',
    data: body
  })
}

export function csjBind(data, version = 'v1') {
  return request({
    url: `/uc/${version}/placement/csj/bind`,
    method: 'post',
    data: data
  })
}

export function placementCsj(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/csj`,
    method: 'get',
    params
  })
}

export function accountCsj(version = 'v1') {
  return request({
    url: `/uc/${version}/placement/csj/account`,
    method: 'get'
  })
}

export function networksCsj(companyId, version = 'v1') {
  return request({
    url: `/uc/${version}/placement/csj/network/${companyId}`,
    method: 'get'
  })
}

export function createBindAdSlot(appCode, body, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot`,
    method: 'post',
    data: body
  })
}

export function getCreateBindAdSlotStatus(appCode, execKey, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/${execKey}`,
    method: 'get'
  })
}

export function getCreateBindAdSlotResult(appCode, execKey, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/${execKey}/result`,
    method: 'get'
  })
}

export function createRetry(appCode, id, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/${id}/create/retry`,
    method: 'post'
  })
}

export function bindRetry(appCode, id, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/${id}/bind/retry`,
    method: 'post'
  })
}

export function getMediaApp(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/media/app`,
    method: 'get',
    params
  })
}

export function saveMediaApp(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/placement/adslot/media/app`,
    method: 'post',
    data
  })
}
