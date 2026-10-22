import globals from '@/config/global'
import request from '@/utils/request'
import { advertisers } from '@/api/common'
import { getAdvertiser, ruleAdvertisers } from '@/api/put/common'
import { getUrl, tencentApplications, tencentAuthorize } from '@/api/put/advertiser'
import Total from '@/views/launch/total.vue'
import { shallowMount } from '@vue/test-utils'
import _ from 'underscore'

jest.mock('@/utils/request', () => ({ __esModule: true, default: jest.fn(() => Promise.resolve({ data: {} })) }))
jest.mock('@/utils/auth', () => ({ getAppCode: () => 'demo' }))

describe('腾讯营销页面接入', () => {
  beforeEach(() => request.mockReset().mockResolvedValue({ data: {} }))

  it('投放筛选包含腾讯，同时保持变现平台和安装渠道的含义', () => {
    expect(globals.SOURCE_MAP.tx).toBe('腾讯营销')
    expect(globals.AD_PLATFORM_MAP.tx).toBeUndefined()
    expect(globals.AD_PLATFORM_MAP.ylh).toBe('优量汇')
    expect(globals.CHANNEL_MAP[11]).toBe('广点通推广11')
  })

  it('整体基础数据复用已有平台下拉框，选择腾讯后提交 platform=tx', async () => {
    global._ = _
    request.mockResolvedValue({ data: { total: 0, records: [], total_data: {} } })
    const wrapper = shallowMount(Total, {
      mocks: { $global: globals, $store: { getters: { userInfo: { apps: [] } } } },
      stubs: {
        'el-select': { props: ['value'], template: '<select :value="value" @change="$emit(\'input\', $event.target.value)"><slot /></select>' },
        'el-option': { props: ['value', 'label'], template: '<option :value="value">{{ label }}</option>' },
        'el-row': true, 'el-col': true, 'el-date-picker': true, 'el-button': true,
        'el-table': true, 'el-table-column': true
      },
      directives: { loading() {} }
    })
    await wrapper.vm.$nextTick()
    // 现有平台、投放方式、应用三个下拉框，腾讯只是平台框内的一个选项。
    expect(wrapper.findAll('select').length).toBe(3)
    const platform = wrapper.findAll('select').at(0)
    expect(platform.find('option[value="tx"]').text()).toBe('腾讯营销')
    platform.element.value = 'tx'
    await platform.trigger('change')
    wrapper.vm.handleFilter()
    expect(request).toHaveBeenLastCalledWith(expect.objectContaining({
      url: '/uc/v1/launch/total', data: expect.objectContaining({ platform: 'tx', app_code: 'demo' })
    }))
    wrapper.destroy()
    delete global._
  })

  it('公共广告主查询保留腾讯，仅规则选择器过滤腾讯', async () => {
    const records = [{ advertiser_id: '1', platform: 'jl' }, { advertiser_id: '2', platform: 'tx' }]
    request.mockResolvedValue({ data: { records } })
    expect((await advertisers('demo')).data.records).toEqual(records)
    expect(request).toHaveBeenLastCalledWith({ url: '/uc/v1/mkt/demo/advertiser', method: 'get' })
    expect((await ruleAdvertisers('demo')).data.records).toEqual([records[0]])
    expect(records).toHaveLength(2)
  })

  it('计划规则只在规则接口封装中筛选广告主，不向公共接口传排除参数', async () => {
    const list = [{ advertiser_id: '1', platform: 'ks' }, { advertiser_id: '2', platform: 'tx' }]
    request.mockResolvedValue({ data: { list } })
    expect((await getAdvertiser({ app_code: 'demo' })).data.list).toEqual([list[0]])
    expect(request).toHaveBeenLastCalledWith({ url: '/uc/v1/mkt/getAdvertiser', method: 'post', params: { app_code: 'demo' } })
    expect(list).toHaveLength(2)
  })

  it('授权由后端生成地址，前端只传开发者应用 ID', () => {
    tencentApplications()
    tencentAuthorize('123')
    expect(request.mock.calls[0][0]).toEqual({ url: '/uc/v1/mkt/tx/applications', method: 'get' })
    expect(request.mock.calls[1][0].data).toEqual({ app_id: '123' })
  })

  it('监测地址继续从现有接口获取', () => {
    getUrl('demo')
    expect(request).toHaveBeenCalledWith({ url: '/uc/v1/put/url/demo', method: 'post' })
  })
})
