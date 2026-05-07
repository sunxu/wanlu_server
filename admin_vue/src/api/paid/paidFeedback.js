import request from '@/utils/request'

export function getList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/feedback`,
    method: 'get',
    params
  })
}
