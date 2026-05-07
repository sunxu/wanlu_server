import Layout from '@/layout'

export default {
  path: '/revenue',
  component: Layout,
  name: 'revenue',
  alwaysShow: true,
  meta: {
    title: '媒体变现',
    icon: 'el-icon-pie-chart',
    authName: '/revenue',
    authType: 'common'
  },
  children: [
    {
      path: 'total',
      component: () => import('@/views/revenue/total'),
      name: 'advertiser',
      meta: {
        title: '回收统计',
        authName: '/revenue/total',
        authType: 'common',
        apis: [
          { method: 'get', path: '/uc/{version}/revenue/app' },
          { method: 'get', path: '/uc/{version}/revenue/company' }
        ]
      }
    }
  ]
}
