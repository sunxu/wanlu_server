/** When your routing table is too long, you can split it into small modules **/

import Layout from '@/layout'

const navbarRouter = {
  path: '/navbar',
  component: Layout,
  hidden: true,
  redirect: '/navbar/edit-password',
  name: 'navbar',
  meta: {
    title: '设置'
  },
  children: [
    {
      path: 'edit-password',
      component: () => import('@/views/navbar/edit-password'),
      name: 'edit-password',
      meta: {
        title: '修改密码'
      }
    }
  ]
}
export default navbarRouter
