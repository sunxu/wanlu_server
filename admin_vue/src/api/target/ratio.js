import request from '@/utils/request'
//指标比例
export function ratioList(query) {
    return request({
        url: '/uc/v1/target/ratio',
        method: 'post',
        params: query
    })
}
