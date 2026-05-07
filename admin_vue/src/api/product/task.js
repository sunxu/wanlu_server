import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/product/vendor',
    method: 'post',
    data: body
  })
}

export function saveData(appCode,params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/task/save`,
    method: 'post',
    data:params
  })
}

export function getDetail(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/task/detail`,
    method: 'get',
    params
  })
}

export function getTask(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/task/list`,
    method: 'get',
    params
  })
}
