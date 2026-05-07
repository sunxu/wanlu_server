/** When your routing table is too long, you can split it into small modules **/

import Layout from '@/layout'
const cashRouter = {
  path: '/launch',
  component: Layout,
  name: '推广数据报表',
  meta: {
    title: '推广数据报表',
    icon: 'table',
    authName: '/launch'
  },
  children: [
    // {
    //   path: '/launch/lad',
    //   component: () => import('@/views/launch/lad'),
    //   meta: {
    //     title: '广告投放数据',
    //     authName: '/launch/lad',
    //     apis: [
    //       { method: 'post', path: '/uc/{version}/launch/getAdPlan' },
    //       { method: 'post', path: '/uc/{version}/launch/getAdVer' },
    //       { method: 'post', path: '/uc/{version}/launch/adList' },
    //       { method: 'post', path: '/uc/{version}/launch/planList' },
    //       { method: 'post', path: '/uc/{version}/launch/changeStatus' },
    //       { method: 'post', path: '/uc/{version}/launch/changePlanStatus' },
    //     ]
    //   }
    // },
    {
      path: '/launch/ladnew',
      component: () => import('@/views/launch/ladnew'),
      meta: {
        title: '投放数据',
        authName: '/launch/ladnew',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/getAdPlan' },
          { method: 'post', path: '/uc/{version}/launch/getAdVer' },
          { method: 'post', path: '/uc/{version}/launch/adListNew' },
          { method: 'post', path: '/uc/{version}/launch/planListNew' },
          { method: 'post', path: '/uc/{version}/launch/changeStatus' },
          { method: 'post', path: '/uc/{version}/launch/changePlanStatus' },
          { method: 'post', path: '/uc/{version}/launch/ad' },
          { method: 'post', path: '/uc/{version}/launch/ad/keep' },
          { method: 'post', path: '/uc/{version}/launch/project' },
          { method: 'post', path: '/uc/{version}/launch/project/keep' },
          { method: 'post', path: '/uc/{version}/launch/advertiser' },
          { method: 'post', path: '/uc/{version}/launch/plan' }
        ]
      }
    },
    {
      path: '/launch/longKeep',
      component: () => import('@/views/launch/longKeep'),
      meta: {
        title: '激活留存',
        authName: '/launch/longKeep',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/getAdPlan' },
          { method: 'post', path: '/uc/{version}/launch/getAdVer' },
          { method: 'post', path: '/uc/{version}/launch/longKeep' },
          { method: 'post', path: '/uc/{version}/launch/longPlan' },
          { method: 'post', path: '/uc/{version}/launch/advertiser/keep' },
          { method: 'post', path: '/uc/{version}/launch/plan/keep' }
        ]
      }
    },
     {
      path: '/launch/longKeepNew',
      component: () => import('@/views/launch/longKeepNew'),
      meta: {
        title: '激活留存新',
        authName: '/launch/longKeepNew',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/getAdPlan' },
          { method: 'post', path: '/uc/{version}/launch/getAdVer' },
          { method: 'post', path: '/uc/{version}/launch/longKeep' },
          { method: 'post', path: '/uc/{version}/launch/longPlan' },
          { method: 'post', path: '/uc/{version}/launch/advertiser/keep' },
          { method: 'post', path: '/uc/{version}/launch/plan/keep' }
        ]
      }
    },
    {
      path: '/launch/total',
      component: () => import('@/views/launch/total'),
      meta: {
        title: '整体基础数据',
        authName: '/launch/total',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/total' },
        ]
      }
    },
    {
      path: '/launch/dailyBase',
      component: () => import('@/views/launch/dailyBase'),
      meta: {
        title: '日报基础数据',
        authName: '/launnch/dailyBase',
        apis: [
          { method: 'get', path: '/uc/{version}/{appCode}/launch/daily/base' }
        ]
      }
    },
    {
      path: '/launch/hour-chart',
      component: () => import('@/views/launch/hour-chart'),
      meta: {
        title: '分小时报表',
        authName: '/launch/hour-chart',
        apis: [
          { method: 'get', path: '/uc/{version}/launch/hour/chart' }
        ]
      }
    },
    {
      path: '/launch/reportadvertiser',
      component: () => import('@/views/launch/reportadvertiser'),
      meta: {
        title: '分时ROI',
        authName: '/launch/reportadvertiser',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/report_advertiser' }
        ]
      }
    },
    {
      path: '/launch/adperformance',
      component: () => import('@/views/launch/adperformance'),
      meta: {
        title: '投放策算',
        authName: '/launch/adperformance',
        apis: [
          { method: 'post', path: '/uc/{version}/launch/ad_performance' }
        ]
      }
    },
    {
      path: '/launch/ladtimenew',
      component: () => import('@/views/launch/ladtimenew'),
      meta: {
        title: '投放分时',
        authName: '/launch/ladtimenew',
        apis: [ 
          { method: 'post', path: '/uc/{version}/launch/getAdPlan' },
          { method: 'post', path: '/uc/{version}/launch/getAdVer' },
          { method: 'post', path: '/uc/{version}/launch/hourly_advertiser' },
          { method: 'post', path: '/uc/{version}/launch/hourly_ad' },
          { method: 'post', path: '/uc/{version}/launch/hourly_project' },
          
          { method: 'post', path: '/uc/{version}/launch/adListNew' },
          { method: 'post', path: '/uc/{version}/launch/planListNew' },
          { method: 'post', path: '/uc/{version}/launch/changeStatus' },
          { method: 'post', path: '/uc/{version}/launch/changePlanStatus' },
          { method: 'post', path: '/uc/{version}/launch/ad' },
          { method: 'post', path: '/uc/{version}/launch/ad/keep' },
          { method: 'post', path: '/uc/{version}/launch/project' },
          { method: 'post', path: '/uc/{version}/launch/project/keep' },
          { method: 'post', path: '/uc/{version}/launch/advertiser' },
          { method: 'post', path: '/uc/{version}/launch/plan' }
        ]
      }
    },
  ]
}
export default cashRouter
