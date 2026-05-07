import Layout from '@/layout'

export default {
  path: '/ad',
  component: Layout,
  name: 'ad',
  alwaysShow: true,
  meta: {
    title: '变现广告管理',
    icon: 'el-icon-money',
    // authType: 'common',
    authName: '/ad'
  },
  children: [
    {
      path: 'strategy',
      component: () => import('@/views/ad/strategy'),
      name: 'strategy',
      meta: {
        title: '广告策略配置',
        // authType: 'common',
        authName: '/ad/strategy',
        apis: [
          { method: 'post', path: '/uc/{version}/ad/strategy/list' },
          { method: 'post', path: '/uc/{version}/ad/strategy/save' },
          { method: 'post', path: '/uc/{version}/ad/strategy/delete' },
          { method: 'post', path: '/uc/{version}/ad/strategy/status' }
        ]
      }
    },
    // {
    //   path: 'placement-map',
    //   component: () => import('@/views/ad/placement_map'),
    //   name: 'placement-map',
    //   meta: {
    //     title: '代码位映射表',
    //     // authType: 'common',
    //     authName: '/ad/placement-map',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/ad/placement-map/list' },
    //       { method: 'post', path: '/uc/{version}/ad/placement-map/delete' },
    //       { method: 'post', path: '/uc/{version}/ad/placement-map/save' },
    //       { method: 'post', path: '/uc/{version}/ad/placement-map/export' },
    //       { method: 'post', path: '/uc/{version}/ad/placement-map/import' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/upload' }
    //     ]
    //   }
    // },
    // {
    //   path: 'placement-create',
    //   component: () => import('@/views/ad/placement_create'),
    //   name: 'placement-create',
    //   meta: {
    //     title: '创建代码位',
    //     // authType: 'common',
    //     authName: '/ad/placement-create',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/ad/placement/account-list' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/app-list' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/app-query' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/create-placement' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/bind-topon' },
    //       { method: 'post', path: '/uc/{version}/ad/placement/upload' },
    //       { method: 'post', path: '/uc/{version}/placement/csj/export' },
    //       { method: 'post', path: '/uc/{version}/placement/csj/bind' },
    //       { method: 'get', path: '/uc/{version}/{appCode}/placement/csj' },
    //       { method: 'get', path: '/uc/{version}/placement/csj/account' },
    //       { method: 'get', path: '/uc/{version}/placement/csj/network/{companyId}' },
    //       { method: 'get', path: '/uc/{version}/app/all' }
    //     ]
    //   }
    // },
    {
      path: 'ad-slot',
      component: () => import('@/views/ad/ad-slot'),
      name: 'ad-slot',
      meta: {
        title: '创建/绑定代码位',
        // authType: 'common',
        authName: '/ad/ad-slot',
        apis: [
          { method: 'post', path: '/uc/{version}/{appCode}/placement/adslot' },
          { method: 'get', path: '/uc/{version}/{appCode}/placement/adslot/{execKey}' },
          { method: 'get', path: '/uc/{version}/{appCode}/placement/adslot/{execKey}/result' },
          { method: 'post', path: '/uc/{version}/{appCode}/placement/adslot/{id}/create/retry' },
          { method: 'post', path: '/uc/{version}/{appCode}/placement/adslot/{id}/bind/retry' },
          { method: 'get', path: '/uc/{version}/{appCode}/placement/adslot/media/app' },
          { method: 'post', path: '/uc/{version}/{appCode}/placement/adslot/media/app' }
        ]
      }
    },
    {
      path: 'media-app-bd',
      component: () => import('@/views/ad/ad-slot/media-app-bd'),
      name: 'media-app-bd',
      hidden: true,
      meta: {
        title: '百度 APP配置'
      }
    },
    {
      path: 'ascribe',
      component: () => import('@/views/ad/ascribe'),
      name: 'ascribe',
      meta: {
        title: '测试归因设置',
        // authType: 'common',
        authName: '/ad/ascribe',
        apis: [
          { method: 'post', path: '/uc/{version}/ad/ascribe/query' },
          { method: 'post', path: '/uc/{version}/ad/ascribe/save' }
        ]
      }
    }
  ]
}
