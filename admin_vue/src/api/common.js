import request from '@/utils/request'

export function advertiserProjectAd(appCode) {
  return request({
    url: `/uc/v1/mkt/${appCode}/advertiser/project/ad`,
    method: 'get'
  })
}

export function advertiserProject(appCode) {
  return request({
    url: `/uc/v1/mkt/${appCode}/advertiser/project`,
    method: 'get'
  })
}

export function advertiserPlan(appCode) {
  return request({
    url: `/uc/v1/mkt/${appCode}/advertiser/plan`,
    method: 'get'
  })
}

export function advertisers(appCode) {
  return request({
    url: `/uc/v1/mkt/${appCode}/advertiser`,
    method: 'get'
  })
}

export function agencys(appCode) {
  return request({
    url: `/uc/v1/mkt/${appCode}/agency`,
    method: 'get'
  })
}

export function appsAll(version = 'v1') {
  return request({
    url: `/uc/${version}/app/all`,
    method: 'get'
  })
}

export function companyList(version = 'v1') {
  return request({
    url: `/uc/${version}/company`,
    method: 'get'
  })
}
