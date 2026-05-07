import request from '@/utils/request'
import { data } from 'autoprefixer'
//获取广告计划
export function getAdvertisPlan(query) {
  return request({
    url: '/uc/v1/launch/getAdPlan',
    method: 'post',
    data: query
  })
}

// 获取广告
export function getAdvertis(data) {
  return request({
    url: '/uc/v1/launch/getAdVer',
    method: 'post',
    data
  })
}

export function adList(query) {
  return request({
    url: '/uc/v1/launch/adList',
    method: 'post',
    params: query
  })
}
export function planList(query) {
  return request({
    url: '/uc/v1/launch/planList',
    method: 'post',
    params: query
  })
}

export function changeStatus(query) {
  return request({
    url: '/uc/v1/launch/changeStatus',
    method: 'post',
    params: query
  })
}

export function changePlanStatus(query) {
  return request({
    url: '/uc/v1/launch/changePlanStatus',
    method: 'post',
    params: query
  })
}

export function adListNew(query) {
  return request({
    url: '/uc/v1/launch/adListNew',
    method: 'post',
    params: query
  })
}

export function planListNew(query) {
  return request({
    url: '/uc/v1/launch/planListNew',
    method: 'post',
    params: query
  })
}

//激活留存 广告主
export function longKeep(query) {
  return request({
    url: '/uc/v1/launch/longKeep',
    method: 'post',
    params: query
  })
}
//激活留存 广告计划
export function longPlan(query) {
  return request({
    url: '/uc/v1/launch/longPlan',
    method: 'post',
    params: query
  })
}
export function getLaunchAdTimeNew(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/hourly_ad`,
    method: 'post',
    data: data
  })
}
export function getProjectTimeNew(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/hourly_project`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdvertiserTimeNew(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/hourly_advertiser`,
    method: 'post',
    data: data
  })
}

export function getAdFilterTimeNew(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/hourly_ad_filter`,
    method: 'post',
    data: data  
  })
}
export function getSearchFilterNew(data, version = 'v1') {
 
  return request({
    url: `/uc/${version}/launch/hourly_filter`,
    method: 'post',
    data: data
  })
}

export function getLaunchAd(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/ad`,
    method: 'post',
    data: data
  })
}

export function getLaunchPlan(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/plan`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdvertiser(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/advertiser`,
    method: 'post',
    data: data
  })
}
export function getReportAdvertiser(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/report_advertiser`,
    method: 'post',
    data: data
  })
}
export function getAdPerformance(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/ad_performance`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdProject(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/project`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdKeep(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/ad/keep`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdvertiserKeep(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/advertiser/keep`,
    method: 'post',
    data: data
  })
}

export function getLaunchPlanKeep(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/plan/keep`,
    method: 'post',
    data: data
  })
}

export function getLaunchAdProjectKeep(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/project/keep`,
    method: 'post',
    data: data
  })
}

export function getLaunchTotal(data, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/total`,
    method: 'post',
    data: data
  })
}

export function launchDailyBase(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/launch/daily/base`,
    method: 'get',
    params
  })
}

export function launchHourChart(params, version = 'v1') {
  return request({
    url: `/uc/${version}/launch/hour/chart`,
    method: 'get',
    params
  })
}
