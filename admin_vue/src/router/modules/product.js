import Layout from '@/layout'

export default {
  path: '/product',
  component: Layout,
  name: 'product',
  meta: {
    title: '产品数据报表',
    icon: 'el-icon-data-analysis',
    authName: '/product'
  },
  children: [
    {
      path: 'keep',
      component: () => import('@/views/product/keep'),
      name: 'keep',
      meta: {
        title: '留存用户',
        authName: '/product/keep',
        apis: [
          { method: 'post', path: '/uc/{version}/product/keep' },
          { method: 'post', path: '/uc/{version}/product/app' }
        ]
      }
    },
    {
      path: 'vendor',
      component: () => import('@/views/product/vendor'),
      name: 'vendor',
      meta: {
        title: '厂商分布',
        authName: '/product/vendor',
        apis: [
          { method: 'post', path: '/uc/{version}/product/vendor' },
          { method: 'post', path: '/uc/{version}/product/app' },
          { method: 'post', path: '/uc/{version}/product/advertiser' }
        ]
      }
    },
    {
      path: 'task',
      component: () => import('@/views/product/task'),
      name: 'task',
      meta: {
        title: '定制报表',
        authName: '/product/task',
        apis: [
          { method: 'post', path: '/uc/{version}/{appCode}/task/save' },
          { method: 'get', path: '/uc/{version}/{appCode}/task/list' },
          { method: 'get', path: '/uc/{version}/{appCode}/task/detail' },
        ]
      }
    },
    {
      path: 'guiyin',
      component: () => import('@/views/product/guiyin'),
      name: 'task',
      meta: {
        title: '设备归因',
        authName: '/product/guiyin',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/guiyin/list' },
        ]
      }
    },
    {
      path: 'device-ecpm-event',
      component: () => import('@/views/product/device-ecpm-event'),
      name: 'task',
      meta: {
        title: '用户行为分布',
        authName: '/product/device-ecpm-event',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/product/deviceecpmevent' },
          { method: 'get', path: '/uc/{version}/{appCode}/product/deviceecpmevent/condition' }
        ]
      }
    },
    {
      path: '/product/dailyBase',
      component: () => import('@/views/product/dailyBase'),
      name: 'task',
      meta: {
        title: '整体基础数据',
        authName: '/product/dailyBase',
        apis: [
          { method: 'get', path: '/uc/{version}/product/dailyBase' }
        ]
      }
    }
  ]
}
