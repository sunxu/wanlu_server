/** When your routing table is too long, you can split it into small modules **/

import Layout from '@/layout'

const settingRouter = {
  path: '/setting',
  component: Layout,
  redirect: '/setting/role-table',
  name: 'setting',
  meta: {
    title: '设置',
    icon: 'el-icon-setting',
    authName: '/setting',
    authType: 'common'
  },
  children: [
    {
      path: 'user-table',
      component: () => import('@/views/setting/user-table'),
      name: 'user-table',
      meta: {
        title: '用户管理',
        authName: '/setting/user-table',
        authType: 'common',
        apis: [
          { method: 'get', path: '/uc/{version}/user' },
          { method: 'post', path: '/uc/{version}/user' },
          { method: 'put', path: '/uc/{version}/user/{userId}' },
          { method: 'put', path: '/uc/{version}/user/{userId}/password' },
          { method: 'put', path: '/uc/{version}/user/{userId}/status' }
        ]
      }
    },
    {
      path: 'role-table',
      component: () => import('@/views/setting/role-table'),
      name: 'role-table',
      meta: {
        title: '角色管理',
        authName: '/setting/role-table',
        authType: 'common',
        apis: [
          { method: 'get', path: '/uc/{version}/role' },
          { method: 'put', path: '/uc/{version}/role/{roleId}' },
          { method: 'post', path: '/uc/{version}/role' }
        ]
      }
    },
    {
      path: 'app-table',
      component: () => import('@/views/setting/app-table'),
      name: 'app-table',
      meta: {
        title: 'APP 管理',
        authName: '/setting/app-table',
        authType: 'common',
        apis: [
          { method: 'get', path: '/uc/{version}/app' },
          { method: 'put', path: '/uc/{version}/app/{appId}/auth' },
          { method: 'put', path: '/uc/{version}/app/{appId}/status' },
          { method: 'put', path: '/uc/{version}/app/{appId}/putStatus' }
        ]
      }
    }
  ]
}
export default settingRouter
