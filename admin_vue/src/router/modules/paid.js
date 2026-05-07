import Layout from '@/layout'

export default {
  path: '/paid',
  component: Layout,
  name: 'paid',
  alwaysShow: true,
  meta: {
    title: '付费服务',
    icon: 'el-icon-money',
    // authType: 'common',
    authName: '/paid'
  },
  children: [
    {
      path: 'paid-goods',
      component: () => import('@/views/paid/paid-goods'),
      name: 'paid-goods',
      meta: {
        title: 'VIP 付费套餐配置',
        // authType: 'common',
        authName: '/paid/paid-goods',
        apis: [
          { method: 'get', path: '/uc/{version}/paid/goods' },
          { method: 'post', path: '/uc/{version}/paid/goods' },
          { method: 'put', path: '/uc/{version}/paid/goods/{goodsNo}' },
          { method: 'put', path: '/uc/{version}/paid/goods/{goodsNo}/enable' }
        ]
      }
    },
    {
      path: 'paid-feedback',
      component: () => import('@/views/paid/paid-feedback'),
      name: 'paid-feedback',
      meta: {
        title: '意见反馈',
        // authType: 'common',
        authName: '/paid/paid-feedback',
        apis: [
          { method: 'get', path: '/uc/{version}/paid/feedback' }
        ]
      }
    },
    {
      path: 'paid-report-daily-recharge',
      component: () => import('@/views/paid/paid-report-daily-recharge'),
      name: 'paid-report-daily-recharge',
      meta: {
        title: '日充值数据',
        // authType: 'common',
        authName: '/paid/report/daily/recharge',
        apis: [
          { method: 'get', path: '/uc/{version}/paid/report/daily/recharge' }
        ]
      }
    }
  ]
}
