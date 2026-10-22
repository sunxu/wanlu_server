import request from '@/utils/request'
import { advertisers } from '@/api/common'

// 自动规则暂不支持腾讯；公共广告主查询仍返回所有平台。
export function ruleAdvertisers(appCode) {
    return advertisers(appCode).then(response => ({
        ...response,
        data: { ...response.data, records: response.data.records.filter(item => item.platform !== 'tx') }
    }))
}
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
    }).then(response => ({
        ...response,
        data: { ...response.data, list: response.data.list.filter(item => item.platform !== 'tx') }
    }))
}
