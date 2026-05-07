import request from '@/utils/request'

// 报警规则相关API
export function alertRuleList(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule/list`,
    method: 'post',
    data
  })
}

export function saveAlertRule(appCode, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule`,
    method: 'post',
    data
  })
}

export function updateAlertRule(appCode, ruleId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule/${ruleId}`,
    method: 'put',
    data
  })
}

export function alertRuleInfo(appCode, ruleId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule/${ruleId}`,
    method: 'get'
  })
}

export function enableAlertRule(appCode, ruleId, enable, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule/${ruleId}/enable`,
    method: 'put',
    data: { enable }
  })
}

export function deleteAlertRule(appCode, ruleId, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/put/alert-rule/${ruleId}`,
    method: 'delete'
  })
}

// 代投配置相关API
export function alertDelegateList(data, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate/list`,
    method: 'post',
    data
  })
}

export function saveAlertDelegate(data, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate`,
    method: 'post',
    data
  })
}

export function updateAlertDelegate(delegateId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate/${delegateId}`,
    method: 'put',
    data
  })
}

export function alertDelegateInfo(delegateId, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate/${delegateId}`,
    method: 'get'
  })
}

export function deleteAlertDelegate(delegateId, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate/${delegateId}`,
    method: 'delete'
  })
}

export function testAlertDelegateDing(delegateId, version = 'v1') {
  return request({
    url: `/uc/${version}/put/alert-delegate/${delegateId}/test`,
    method: 'post'
  })
}
