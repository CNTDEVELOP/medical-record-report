<!-- resources/js/Pages/Auth/Login.vue -->
<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'

const showPwd = ref(false)

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post('/login', {
    preserveScroll: true,
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 grid place-items-center p-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-md p-6">
      <!-- Header -->
      <div class="flex items-center gap-3 mb-6">
        <div class="size-10 rounded-xl bg-blue-600 grid place-items-center text-white font-bold">
          DB
        </div>
        <div>
          <h1 class="text-xl font-semibold leading-tight">เข้าสู่ระบบ</h1>
          <p class="text-gray-500 text-sm">Dental Booking System</p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <!-- Email -->
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">อีเมล</label>
          <input
            id="email"
            type="email"
            v-model="form.email"
            required
            autocomplete="email"
            class="w-full rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent px-3 py-2 bg-white"
            placeholder="you@example.com"
          />
          <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">
            {{ form.errors.email }}
          </div>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">รหัสผ่าน</label>
            <Link href="/forgot-password" class="text-sm text-blue-600 hover:underline">ลืมรหัสผ่าน?</Link>
          </div>

          <div class="relative">
            <input
              :type="showPwd ? 'text' : 'password'"
              id="password"
              v-model="form.password"
              required
              autocomplete="current-password"
              class="w-full rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent px-3 py-2 pr-10 bg-white"
              placeholder="••••••••"
            />
            <button
              type="button"
              @click="showPwd = !showPwd"
              class="absolute inset-y-0 right-0 px-3 grid place-items-center text-gray-500 hover:text-gray-700"
              aria-label="toggle password"
              :title="showPwd ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน'"
            >
              <svg v-if="!showPwd" xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M2.036 12.322a1.012 1.012 0 010-.644C3.423 7.51 7.36 5 12 5c4.64 0 8.577 2.51 9.964 6.678.07.204.07.44 0 .644C20.577 16.49 16.64 19 12 19c-4.64 0-8.577-2.51-9.964-6.678z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M3.98 8.223A10.477 10.477 0 001.934 12C3.32 16.168 7.258 18.677 11.898 18.677c1.64 0 3.194-.32 4.6-.902M6.6 6.6A10.5 10.5 0 0111.898 5.323c4.64 0 8.577 2.51 9.964 6.678a1.012 1.012 0 010 .644 10.51 10.51 0 01-2.06 3.777M3 3l18 18" />
              </svg>
            </button>
          </div>

          <div v-if="form.errors.password" class="mt-1 text-sm text-red-600">
            {{ form.errors.password }}
          </div>
        </div>

        <!-- Remember -->
        <label class="flex items-center gap-2 select-none">
          <input type="checkbox" v-model="form.remember" class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
          <span class="text-sm text-gray-700">จดจำการเข้าสู่ระบบ</span>
        </label>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="form.processing"
          class="w-full rounded-xl bg-blue-600 text-white font-semibold py-2.5 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
        >
          <span v-if="!form.processing">เข้าสู่ระบบ</span>
          <span v-else class="inline-flex items-center gap-2">
            <svg class="size-4 animate-spin" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity=".25"/><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4" fill="none"/></svg>
            กำลังเข้าสู่ระบบ...
          </span>
        </button>
      </form>

      <!-- Footer -->
      <p class="text-sm text-gray-600 mt-6 text-center">
        ยังไม่มีบัญชี?
        <Link href="/register" class="text-blue-600 hover:underline">สมัครสมาชิก</Link>
      </p>
    </div>
  </div>
</template>
