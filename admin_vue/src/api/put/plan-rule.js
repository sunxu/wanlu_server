import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/put/plan-rule/list',
    method: 'post',
    data: body
  })
}

export function bindPlan(body) {
  return request({
    url: '/uc/v1/put/plan-rule/bind',
    method: 'post',
    data: body
  })
}

export function saveData(body) {
  return request({
    url: '/uc/v1/put/plan-rule/save',
    method: 'post',
    data: body
  })
}

export function changeStatus(body) {
  return request({
    url: '/uc/v1/put/plan-rule/status',
    method: 'post',
    data: body
  })
}

export function deleteData(body) {
  return request({
    url: '/uc/v1/put/plan-rule/delete',
    method: 'post',
    data: body
  })
}
