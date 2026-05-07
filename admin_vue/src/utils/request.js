import axios from 'axios'
import { MessageBox, Message } from 'element-ui'
import store from '@/store'
import { getToken } from '@/utils/auth'
import md5 from 'js-md5'
import JSONBIG from 'json-bigint'
import { getUuid } from '@/utils/index'

// create an axios instance
const service = axios.create({
  baseURL: process.env.VUE_APP_BASE_API, // url = base url + request url
  // withCredentials: true, // send cookies when cross-domain requests
  timeout: 1000 * 60 // request timeout
})

service.defaults.transformResponse = [
  data => {
    const json = JSONBIG({
      storeAsString: true
    })
    return json.parse(data)
  }
]

const str1Header = ['X-ABBR', 'X-APPCODE', 'X-APPID', 'X-NONCESTR', 'X-RUN', 'X-TIMESTAMP', 'X-TRICE']

// request interceptor
service.interceptors.request.use(
  config => {
    // do something before request is sent

    if (store.getters.token) {
      // let each request carry token
      // ['X-Token'] is a custom headers key
      // please modify it according to the actual situation
      config.headers['X-Token'] = getToken()
    }
    config.headers['X-ABBR'] = 'web'
    config.headers['X-APPCODE'] = process.env.VUE_APP_APPCODE
    config.headers['X-APPID'] = process.env.VUE_APP_APPID
    config.headers['X-NONCESTR'] = md5(getUuid())
    config.headers['X-RUN'] = process.env.VUE_APP_APPRUN
    config.headers['X-TIMESTAMP'] = Math.round(new Date().getTime() / 1000)
    config.headers['X-TRICE'] = getUuid()
    // 计算签名
    let str1 = ''
    for (let i = 0; i < str1Header.length; i++) {
      if (str1Header[i] === 'X-NONCESTR') {
        str1 += '&' + str1Header[i] + '=' + config.headers[str1Header[i]].substr(0, 8)
        continue
      }
      str1 += '&' + str1Header[i] + '=' + config.headers[str1Header[i]]
    }
    str1 = str1.substr(1).toLowerCase()
    config.headers['X-SIGN'] = md5(str1 + process.env.VUE_APP_SIGN_SECRET).substr(5, 10)
    return config
  },
  error => {
    // do something with request error
    console.log(error) // for debug
    return Promise.reject(error)
  }
)

// response interceptor
service.interceptors.response.use(
  /**
   * If you want to get http information such as headers or status
   * Please return  response => response
  */

  /**
   * Determine the request status by custom code
   * Here is just an example
   * You can also judge the status by HTTP Status Code
   */
  response => {
    const res = response.data

    // if the custom code is not 20000, it is judged as an error.
    if (res.code !== 0) {
      Message({
        message: res.msg || 'Error',
        type: 'error',
        duration: 5 * 1000
      })

      // 50008: Illegal token; 50012: Other clients logged in; 50014: Token expired;
      if (res.code === 410) {
        // to re-login
        MessageBox.confirm('重新登录', 'Confirm logout', {
          confirmButtonText: 'Re-Login',
          cancelButtonText: 'Cancel',
          type: 'warning'
        }).then(() => {
          store.dispatch('user/resetToken').then(() => {
            location.reload()
          })
        })
      }
      return Promise.reject(new Error(res.msg || 'Error'))
    } else {
      return res
    }
  },
  error => {
    console.log('err' + error) // for debug
    Message({
      message: error.message,
      type: 'error',
      duration: 5 * 1000
    })
    return Promise.reject(error)
  }
)

//get 请求携带参数
export function get(url,params){
  return service({
    method:'get',
    url,
    params,
    timeout:1000,
    headers:{
      'X-requested-With':'XMLHttpRequest'
    }
  })
}
export function post(url,params){
  return service({
    method:'post',
    url,
    data:qs.stringify(data),
    timeout:1000,
    headers:{
      'X-requested-With':'XMLHttpRequest',
      'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'
    }
  })
}
export default service
