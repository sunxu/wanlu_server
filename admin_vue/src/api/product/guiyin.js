import request from '@/utils/request'

export function getList(appCode, params, version = 'v1') {
  return request({
    url: `/uc/${version}/${appCode}/guiyin/list`,
    method: 'get',
    params
  })
}
