/** When your routing table is too long, you can split it into small modules **/

import Layout from '@/layout'

const profitRouter = {
  path: '/profit',
  component: Layout,
  redirect: '/profit/withdrawal-table',
  name: 'profit',
  meta: {
    title: '网赚',
    icon: 'table',
    authName: '/profit'
  },
  children: [
    // {
    //   path: 'userTable',
    //   component: () => import('@/views/profit/user-table'),
    //   name: 'userTable',
    //   meta: {
    //     title: '用户',
    //     authName: '/profit/userTable',
    //     apis: [
    //       { method: 'get', path: '/uc/mis/inner/user/list' }
    //     ]
    //   }
    // },
    {
      path: 'withdrawalTable',
      component: () => import('@/views/profit/withdrawal-table'),
      name: 'withdrawalTable',
      meta: {
        title: '提现总表',
        authName: '/profit/withdrawalTable',
        apis: [
          { method: 'get', path: '/uc/mis/inner/withdrawal/statistics/total' },
          { method: 'get', path: '/uc/mis/inner/withdrawal/statistics/total/export' }
        ]
      }
    },
    {
      path: 'withdrawalItemTable',
      component: () => import('@/views/profit/withdrawal-table-item'),
      name: 'withdrawalItemTable',
      meta: {
        title: '提现子表',
        authName: '/profit/withdrawalItemTable',
        apis: [
          { method: 'get', path: '/uc/mis/inner/withdrawal/statement' },
          { method: 'get', path: '/uc/mis/inner/withdrawal/statement/export' }
        ]
      }
    },
    {
      path: 'taskTable',
      component: () => import('@/views/profit/task-table'),
      name: 'taskTable',
      meta: {
        title: '任务总表',
        authName: '/profit/taskTable',
        apis: [
          { method: 'get', path: '/uc/mis/inner/task/statistics/day' }
        ]
      }
    },
    {
      path: 'taskItemTable',
      component: () => import('@/views/profit/task-table-item'),
      name: 'taskItemTable',
      meta: {
        title: '任务子表',
        authName: '/profit/taskItemTable',
        apis: [
          { method: 'get', path: '/uc/mis/inner/task/statistics/item/day' }
        ]
      }
    }
  ]
}
export default profitRouter
