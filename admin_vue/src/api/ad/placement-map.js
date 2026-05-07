import request from '@/utils/request'

export function getList(body) {
  return request({
    url: '/uc/v1/ad/placement-map/list',
    method: 'post',
    data: body
  })
}

export function deleteData(body) {
  return request({
    url: '/uc/v1/ad/placement-map/delete',
    method: 'post',
    data: body
  })
}

export function saveData(body) {
  return request({
    url: '/uc/v1/ad/placement-map/save',
    method: 'post',
    data: body
  })
}

export function exportData(body) {
  return request({
    url: '/uc/v1/ad/placement-map/export',
    method: 'post',
    data: body
  })
}

export function importData(body) {
  return request({
    url: '/uc/v1/ad/placement-map/import',
    method: 'post',
    data: body
  })
}

export function upload(body) {
  return request({
    url: '/uc/v1/ad/placement/upload',
    method: 'post',
    data: body
  })
}
