import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/ad/strategy/list',
    method: 'post',
    data: body
  })
}

export function saveData(body) {
  return request({
    url: '/uc/v1/ad/strategy/save',
    method: 'post',
    data: body
  })
}

export function deleteData(body) {
  return request({
    url: '/uc/v1/ad/strategy/delete',
    method: 'post',
    data: body
  })
}

export function updateData(body) {
  return request({
    url: '/uc/v1/ad/strategy/status',
    method: 'post',
    data: body
  })
}
