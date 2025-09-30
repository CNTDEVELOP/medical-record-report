import type { App } from 'vue'
import { request, isLoadingAxi } from '@/lib/axiosService'

export default {
  install(app: App) {
    // globalProperties (Options API): this.$request
    app.config.globalProperties.$request = request
    app.config.globalProperties.$isLoadingAxi = isLoadingAxi
    // provide/inject (Composition API):
    app.provide('request', request)
    app.provide('isLoadingAxi', isLoadingAxi)
  },
}
