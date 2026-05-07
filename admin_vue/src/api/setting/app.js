import request from '@/utils/request'

export function getList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/app`,
    method: 'get',
    params
  })
}

export function editAuth(appId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appId}/auth`,
    method: 'put',
    data
  })
}

export function editStatus(appId, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appId}/status`,
    method: 'put'
  })
}

export function editAdstatus(appId, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appId}/putStatus`,
    method: 'put'
  })
}

export function editAdstatus2(appId, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appId}/putStatus2`,
    method: 'put'
  })
}

export function saveApp(data, version = 'v1') {
  return request({
    url: `/uc/${version}/app/save`,
    method: 'put',
    data
  })
}

export function blockSave(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appCode}/block`,
    method: 'put',
    data
  })
}

export function putStatus(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/app/${appCode}/put`,
    method: 'put',
    data
  })
}
