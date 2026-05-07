import request from '@/utils/request'

export function getList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/user`,
    method: 'get',
    params
  })
}

export function storeUser(data, version = 'v1') {
  return request({
    url: `/uc/${version}/user`,
    method: 'post',
    data
  })
}

export function editUser(userId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/user/${userId}`,
    method: 'put',
    data
  })
}

export function editPassword(userId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/user/${userId}/password`,
    method: 'put',
    data
  })
}

export function editStatus(userId, version = 'v1') {
  return request({
    url: `/uc/${version}/user/${userId}/status`,
    method: 'put'
  })
}
