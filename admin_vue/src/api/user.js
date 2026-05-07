import request from '@/utils/request'

export function login(data) {
  return request({
    url: '/uc/v1/login',
    method: 'post',
    data
  })
}

export function getInfo() {
  return request({
    url: '/uc/v1/selfInfo',
    method: 'get',
    params: { }
  })
}

export function editPassword(userId, data, version = 'v1') {
  return request({
    url: `/uc/${version}/self/password`,
    method: 'put',
    data
  })
}

export function logout() {
  return request({
    url: '/uc/v1/logout',
    method: 'post'
  })
}
// 推广数据
export function extension() {
  return request({
    url: '/uc/v1/extension',
    method: 'post'
  })
}
