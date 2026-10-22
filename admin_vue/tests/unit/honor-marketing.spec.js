import globals from '@/config/global'
import request from '@/utils/request'
import Total from '@/views/launch/total.vue'
import Project from '@/views/launch/launch/ad-project.vue'
import Ad from '@/views/launch/launch/ad.vue'
import OldAdvertiser from '@/views/launch/components/advertisere_new.vue'
import DeviceEvents from '@/views/product/device-ecpm-event.vue'
import ProfitUsers from '@/views/profit/user-table.vue'
import { advertisers } from '@/api/common'
import { shallowMount } from '@vue/test-utils'
import _ from 'underscore'

jest.mock('@/utils/request', () => ({ __esModule: true, default: jest.fn(() => Promise.resolve({ data: {} })) }))
jest.mock('@/utils/auth', () => ({ getAppCode: () => 'demo', getCurrentApp: () => ({ app_code: 'demo' }) }))

describe('荣耀投放接入', () => {
  beforeEach(() => request.mockReset().mockResolvedValue({ data: {} }))

  it('荣耀是投放来源7，安装渠道仍为14，腾讯及变现平台不变', () => {
    expect(globals.SOURCE_MAP.honor).toBe('荣耀')
    expect(globals.SOURCE_MAP.tx).toBe('腾讯营销')
    expect(globals.CHANNEL_MAP[14]).toBe('荣耀推广14')
    expect(globals.AD_PLATFORM_MAP.honor).toBeUndefined()
  })

  it('在已有报表下拉框中选择荣耀，发送 platform=honor', async () => {
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
    const platform = wrapper.findAll('select').at(0)
    expect(platform.find('option[value="honor"]').text()).toBe('荣耀')
    platform.element.value = 'honor'
    await platform.trigger('change')
    wrapper.vm.handleFilter()
    expect(request).toHaveBeenLastCalledWith(expect.objectContaining({ data: expect.objectContaining({ platform: 'honor', app_code: 'demo' }) }))
    wrapper.destroy()
    delete global._
  })

  it('项目和广告页面复用全局来源，旧数字筛选和归因查询包含荣耀', () => {
    global._ = _
    const context = { $global: globals, defaultDate: () => [] }
    expect(Project.data.call(context).source_map.honor).toBe('荣耀')
    expect(Ad.data.call(context).source_map.honor).toBe('荣耀')
    expect(OldAdvertiser.data.call(context).platOption).toContainEqual({ id: 7, name: '荣耀' })
    expect(DeviceEvents.data.call(context).sources).toContainEqual({ code: '7', name: '荣耀' })
    expect(ProfitUsers.data.call(context).sources).toContainEqual({ code: 'honor', name: '荣耀' })
    delete global._
  })

  it('公共广告主查询包含荣耀', async () => {
    const records = [{ advertiser_id: '1', platform: 'honor' }, { advertiser_id: '2', platform: 'tx' }]
    request.mockResolvedValue({ data: { records } })
    expect((await advertisers('demo')).data.records).toEqual(records)
    expect(records).toHaveLength(2)
  })
})
