import Cookies from 'js-cookie'
import store from '@/store'

const TokenKey = 'vue_admin_template_token'
const UserInfoKey = 'vue_admin_template_user_info'
const appCodeKey = 'vue_admin_template_app_code'

export function getToken() {
  return Cookies.get(TokenKey)
}

export function setToken(token) {
  return Cookies.set(TokenKey, token)
}

export function removeToken() {
  return Cookies.remove(TokenKey)
}

export function getUserInfo() {
  return Cookies.get(UserInfoKey)
}

export function setUserInfo(pages) {
  return Cookies.set(UserInfoKey, pages)
}

export function setAppCode(appCode) {
  return Cookies.set(appCodeKey, appCode)
}

export function getAppCode() {
  return Cookies.set(appCodeKey)
}

export function removeAppCode() {
  return Cookies.remove(appCodeKey)
}

export function getAppList() {
  return store.getters.userInfo.apps
}

export function getCurrentApp() {
  const apps = store.getters.userInfo.apps
  const index = apps.map(app => app.app_code).indexOf(getAppCode())
  let appInfo = {}
  if (index >= 0) {
    appInfo = store.getters.userInfo.apps[index].auth ? store.getters.userInfo.apps[index] : {}
  }
  return appInfo
}
