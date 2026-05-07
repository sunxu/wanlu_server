import request from '@/utils/request'
//获取广告计划
export function getAdvertiserPlan(query) {
    return request({
        url: '/uc/v1/mkt/getAdvertiserPlan',
        method: 'post',
        params: query
    })
}
//获取广告
export function getAdvertiser(query) {
    return request({
        url: '/uc/v1/mkt/getAdvertiser',
        method: 'post',
        params: query
    })
}