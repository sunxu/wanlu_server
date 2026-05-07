import request from '@/utils/request'

export function cashList(query) {
  return request({
    url: '/uc/v1/cash/cashList',
    method: 'post',
    params: query
  })
}

export function show(query) {
  return request({
    url: '/uc/v1/cash/show',
    method: 'post',
    data: query
  })
}

export function appList(query) {
  return request({
    url: '/uc/v1/product/app',
    method: 'post'
  })
}

export function liveList(appCode, type, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/live/${type}`,
    method: 'get',
    params
  })
}

export function showList(appCode, type, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/show/${type}`,
    method: 'get',
    params
  })
}

export function ecpmList(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/ecpm`,
    method: 'get',
    params
  })
}

export function revenueList(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/revenue`,
    method: 'get',
    params
  })
}

export function revenueMockVariables(appCode, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/revenue/mock/variables`,
    method: 'get'
  })
}

export function modifyRevenueMockVariables(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/revenue/mock/variables`,
    method: 'put',
    data
  })
}

export function revenueMock(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/cash/revenue/mock`,
    method: 'get',
    params
  })
}
