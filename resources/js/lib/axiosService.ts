import axios from 'axios'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

// baseURL จาก .env ผ่าน Vite
let configured = false
function ensureAxiosConfigured() {
  if (configured) return
  axios.defaults.baseURL = import.meta.env.VITE_API_URL || ''
  configured = true
}

// ===== Global loading state (แทน useState ของ Nuxt) =====
export const isLoadingAxi = ref(false)

// ===== token จาก cookie แบบง่าย =====
function getToken(): string {
  const cookie = document.cookie || ''
  const m = cookie.match(/(?:^|;\s*)token=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : ''
}

// ===== public API =====
export async function request(
  method: 'get'|'post'|'put'|'patch'|'delete',
  url: string,
  data?: any,
  auth = true
) {
  ensureAxiosConfigured()
  isLoadingAxi.value = true

  const headers: Record<string, string> = {}
  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  try {
    return await axios({ method, url, data, headers })
  } catch (err: any) {
    const status = err?.response?.status
    // ไม่มี refresh token ที่นี่ — แค่จัดการ redirect เบา ๆ
    if (status === 401 || status === 403) {
      router.visit('/auth/login')
    }
    throw err
  } finally {
    isLoadingAxi.value = false
  }
}
