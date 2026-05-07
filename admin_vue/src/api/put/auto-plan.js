import request from '@/utils/request'

export function autoPlanList(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/list`,
    method: 'post',
    data
  })
}

export function checkoutConflict(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/conflict`,
    method: 'post',
    data
  })
}

export function saveAutoPlan(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto`,
    method: 'post',
    data
  })
}

export function editAutoPlan(appCode, planId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/${planId}`,
    method: 'put',
    data
  })
}

export function editAutoPlanEnable(appCode, planId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/${planId}/enable`,
    method: 'put'
  })
}

export function autoPlanInfo(appCode, planId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/${planId}`,
    method: 'get'
  })
}

export function autoPlanLog(appCode, planId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/${planId}/log`,
    method: 'get'
  })
}

export function autoPlanLogInfo(appCode, planId, logId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/plan/auto/${planId}/log/${logId}`,
    method: 'get'
  })
}
