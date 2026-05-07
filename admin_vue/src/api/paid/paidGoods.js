import request from '@/utils/request'

export function getList(params, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/goods`,
    method: 'get',
    params
  })
}

export function enableGoods(goodsNo, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/goods/${goodsNo}/enable`,
    method: 'put'
  })
}

export function storeGoods(data, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/goods`,
    method: 'post',
    data
  })
}

export function editGoods(goodsNo, data, version = 'v1') {
  return request({
    url: `/uc/${version}/paid/goods/${goodsNo}`,
    method: 'put',
    data
  })
}
