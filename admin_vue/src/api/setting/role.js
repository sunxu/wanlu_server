import request from '@/utils/request'

export function getList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/role`,
    method: 'get',
    params
  })
}

export function storeRole(data, version = 'v1') {
  return request({
    url: `/uc/${version}/role`,
    method: 'post',
    data
  })
}

export function editRole(roleId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/role/${roleId}`,
    method: 'put',
    data
  })
}
