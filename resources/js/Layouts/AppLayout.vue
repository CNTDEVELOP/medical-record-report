<script setup>
import { Link, Head } from '@inertiajs/vue3'
</script>

<template>
  <Head>
    <meta name="color-scheme" content="light dark" />
  </Head>

  <div class="min-h-screen bg-gray-50 text-gray-900">
    <!-- Topbar -->
    <header class="sticky top-0 z-20 bg-white/80 backdrop-blur border-b">
      <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <Link href="/" class="font-semibold">โปรแกรมของหัวหน้ามิลค์</Link>
        <nav class="flex items-center gap-4 text-sm text-gray-600">
          <Link href="/reports/top-diagnosis" class="hover:text-gray-900">ต่างด้าวสิทAL</Link>
        </nav>
      </div>
    </header>

    <!-- Main content -->
    <main class="max-w-6xl mx-auto px-4 py-6">
      <slot />
    </main>

    <!-- Footer -->
    <!-- <footer class="border-t py-6 text-center text-sm text-gray-500">
      © {{ new Date().getFullYear() }} Dental Booking
    </footer> -->
  </div>
</template>
