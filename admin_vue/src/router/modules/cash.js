/** When your routing table is too long, you can split it into small modules **/

import Layout from '@/layout'
const cashRouter = {
  path: '/cash',
  component: Layout,
  name: '变现数据报表',
  meta: {
    title: '变现数据报表',
    icon: 'list',
    authName: '/cash'
  },
  children: [
    {
      path: '/cash/live',
      component: () => import('@/views/cash/live'),
      meta: {
        title: '心跳数据',
        authName: '/cash/live',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/cash/live/{type}' }
        ]
      }
    },
    // {
    //   path: '/cash/list',
    //   component: () => import('@/views/cash/list'),
    //   meta: {
    //     title: '心跳数据-旧',
    //     authName: '/cash/list',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/cash/cashList' },
    //       { method: 'post', path: '/uc/{version}/cash/channel' },
    //       { method: 'post', path: '/uc/{version}/product/app' },
    //     ]
    //   }
    // },
    {
      path: '/cash/ecpm',
      component: () => import('@/views/cash/ecpm'),
      meta: {
        title: '广告 ECPM',
        authName: '/cash/ecpm',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/cash/ecpm' }
        ]
      }
    },
    {
      path: '/cash/revenue',
      component: () => import('@/views/cash/revenue'),
      meta: {
        title: '广告回本率',
        authName: '/cash/revenue',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/cash/revenue' }
        ]
      }
    },
    {
      path: '/cash/show-v2',
      component: () => import('@/views/cash/show-v2'),
      meta: {
        title: '人均展示',
        authName: '/cash/show-v2',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/cash/show/{type}' }
        ]
      }
    },
    // {
    //   path: '/cash/show',
    //   component: () => import('@/views/cash/show'),
    //   meta: {
    //     title: '人均展示-旧',
    //     authName: '/cash/show',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/cash/show' },
    //       { method: 'post', path: '/uc/{version}/cash/channel' },
    //       { method: 'post', path: '/uc/{version}/product/app' },
    //     ]
    //   }
    // },
    // {
    //   path: '/target/ratio',
    //   component: () => import('@/views/target/ratio'),
    //   meta: {
    //     title: '指标比例',
    //     authName: '/target/ratio',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/target/ratio' },
    //     ]
    //   }
    // },
    {
      path: '/cash/revenue-mock',
      component: () => import('@/views/cash/revenue-mock/index'),
      meta: {
        title: '预估回本周期',
        authName: '/cash/revenue-mock',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/cash/revenue/mock' },
          { method: 'get', path: '/uc/{version}/{appCode}/cash/revenue/mock/variables' },
          { method: 'put', path: '/uc/{version}/{appCode}/cash/revenue/mock/variables' }
        ]
      }
    }
  ]
}
export default cashRouter
