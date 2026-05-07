import Layout from '@/layout'

export default {
  path: '/put',
  component: Layout,
  name: 'put',
  alwaysShow: true,
  meta: {
    title: '推广管理',
    icon: 'el-icon-office-building',
    authName: '/put'
  },
  children: [
    {
      path: 'advertiser',
      component: () => import('@/views/put/advertiser'),
      name: 'advertiser',
      meta: {
        title: '广告主管理',
        authName: '/put/advertiser',
        apis: [
          { method: 'post', path: '/uc/{version}/put/advertiser/list' },
          { method: 'post', path: '/uc/{version}/put/advertiser/save' },
          { method: 'post', path: '/uc/{version}/put/advertiser/bind-app' },
          { method: 'post', path: '/uc/{version}/put/advertiser/status' }
        ]
      }
    },
    // {
    //   path: 'plan-rule',
    //   component: () => import('@/views/put/plan_rule'),
    //   name: 'plan-rule',
    //   meta: {
    //     title: '计划规则',
    //     authName: '/put/plan-rule',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/put/plan-rule/list' },
    //       { method: 'post', path: '/uc/{version}/put/plan-rule/save' },
    //       { method: 'post', path: '/uc/{version}/put/plan-rule/bind' },
    //       { method: 'post', path: '/uc/{version}/put/plan-rule/status' },
    //       { method: 'post', path: '/uc/{version}/put/plan-rule/delete' },
    //       { method: 'post', path: '/uc/{version}/mkt/getAdvertiserPlan' },
    //       { method: 'post', path: '/uc/{version}/mkt/getAdvertiser' },
    //     ]
    //   }
    // },
    {
      path: 'url-list',
      component: () => import('@/views/put/url'),
      name: 'url-list',
      meta: {
        title: '投放管理',
        authName: '/put/url-list',
        apis: [
          { method: 'post', path: '/uc/{version}/put/url/{appCode}' },
          { method: 'put', path: '/uc/{version}/app/${appCode}/block' },
          { method: 'put', path: '/uc/{version}/app/${appCode}/put' }
        ]
      }
    },
    {
      path: 'auto-plan',
      component: () => import('@/views/put/auto-plan/index'),
      name: 'auto-plan',
      meta: {
        title: '计划规则',
        authName: '/put/auto-plan',
        apis: [
          { method: 'post', path: '/uc/{version}/{appCode}/put/plan/autov2' },
          { method: 'post', path: '/uc/{version}/{appCode}/put/plan/autov2/conflict' },
          { method: 'post', path: '/uc/{version}/{appCode}/put/plan/autov2/list' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/plan/autov2/{planId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/plan/autov2/{planId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/plan/autov2/{planId}/enable' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/plan/autov2/{planId}/log' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/plan/autov2/{planId}/log/{logId}' }
        ]
      }
    },
    {
      path: 'auto-app',
      component: () => import('@/views/put/auto-app/index'),
      name: 'auto-app',
      meta: {
        title: '应用规则',
        authName: '/put/auto-app',
        apis: [
          { method: 'post', path: '/uc/{version}/{appCode}/put/app/autov2' },
          { method: 'post', path: '/uc/{version}/{appCode}/put/app/autov2/conflict' },
          { method: 'post', path: '/uc/{version}/{appCode}/put/app/autov2/list' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/app/autov2/{planId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/app/autov2/{planId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/app/autov2/{planId}/enable' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/app/autov2/{planId}/log' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/app/autov2/{planId}/log/{logId}' }
        ]
      }
    },
    {
      path: 'alert-rule',
      component: () => import('@/views/put/alert-rule/index'),
      name: 'alert-rule',
      meta: {
        title: '报警规则',
        authName: '/put/alert-rule',
        apis: [
          { method: 'post', path: '/uc/{version}/{appCode}/put/alert-rule/list' },
          { method: 'post', path: '/uc/{version}/{appCode}/put/alert-rule' },
          { method: 'get', path: '/uc/{version}/{appCode}/put/alert-rule/{ruleId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/alert-rule/{ruleId}' },
          { method: 'put', path: '/uc/{version}/{appCode}/put/alert-rule/{ruleId}/enable' },
          { method: 'delete', path: '/uc/{version}/{appCode}/put/alert-rule/{ruleId}' }
        ]
      }
    },
    {
      path: 'alert-delegate',
      component: () => import('@/views/put/alert-delegate/index'),
      name: 'alert-delegate',
      meta: {
        title: '代投配置',
        authName: '/put/alert-delegate',
        apis: [
          { method: 'post', path: '/uc/{version}/put/alert-delegate/list' },
          { method: 'post', path: '/uc/{version}/put/alert-delegate' },
          { method: 'get', path: '/uc/{version}/put/alert-delegate/{delegateId}' },
          { method: 'put', path: '/uc/{version}/put/alert-delegate/{delegateId}' },
          { method: 'delete', path: '/uc/{version}/put/alert-delegate/{delegateId}' },
          { method: 'post', path: '/uc/{version}/put/alert-delegate/{delegateId}/test' }
        ]
      }
    }
  ]
}
