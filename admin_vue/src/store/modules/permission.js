import router, { asyncRoutes, constantRoutes } from '@/router'
import store from '@/store'
import { getAppCode, removeAppCode } from '@/utils/auth'

/**
 * Use meta.role to determine if the current user has permission
 * @param pages
 * @param route
 */
function hasPermission(pages, route) {
  if (route.meta && route.meta.authName) {
    return pages.some(page => route.meta.authName === page)
  } else {
    return true
  }
}

/**
 * Filter asynchronous routing tables by recursion
 * @param routes asyncRoutes
 * @param pages
 */
export function filterAsyncRoutes(routes, pages) {
  const res = []

  routes.forEach(route => {
    const tmp = { ...route }
    if (hasPermission(pages, tmp)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(tmp.children, pages)
      }
      res.push(tmp)
    } else if (tmp.children) {
      tmp.children = filterAsyncRoutes(tmp.children, pages)
      if (tmp.children.length && tmp.children.length > 0) {
        res.push(tmp)
      }
    }
  })

  return res
}

export function commonAsyncRoutes(routes) {
  const res = []

  routes.forEach(route => {
    const tmp = { ...route }
    if (tmp.meta && tmp.meta.authType === 'common') {
      if (tmp.children) {
        res.push(...commonAsyncRoutes(tmp.children))
      }
      res.push(tmp.meta.authName)
    }
  })
  return res
}

const state = {
  routes: [],
  addRoutes: []
}

const mutations = {
  SET_ROUTES: (state, routes) => {
    state.addRoutes = routes
    state.routes = constantRoutes.concat(routes)
  }
}

const actions = {
  generateRoutes({ commit }, data) {
    return new Promise(resolve => {
      const index = store.getters.userInfo.apps.map(app => app.app_code).indexOf(getAppCode())
      let app_pages = []
      if (index >= 0) {
        app_pages = store.getters.userInfo.apps[index].auth ? store.getters.userInfo.apps[index].auth.pages : []
      } else {
        removeAppCode()
        app_pages = []
      }
      app_pages.push(...commonAsyncRoutes(asyncRoutes))
      let accessedRoutes
      if (data.is_super === 1) {
        // accessedRoutes = asyncRoutes || []
        accessedRoutes = filterAsyncRoutes(asyncRoutes, app_pages)
      } else {
        let newarr = []
        newarr = app_pages.filter((v) => data.pages.indexOf(v) > -1)
        accessedRoutes = filterAsyncRoutes(asyncRoutes, newarr)
      }
      commit('SET_ROUTES', accessedRoutes)
      resolve(accessedRoutes)
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
