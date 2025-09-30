<template>
  <div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-semibold mb-4">วันเปิดรับบริการ</h1>

    <!-- Responsive: 1 คอลัมน์บนมือถือ / 2 คอลัมน์เมื่อ lg+ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- LEFT (Date range) -->
      <aside class="lg:sticky lg:top-20">
        <div class="rounded-xl border bg-white p-4">
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm text-gray-600">ช่วงวันที่</label>
          </div>
          <DatePicker
            inline
            v-model="range"
            selectionMode="range"
            dateFormat="yy-mm-dd"
            showIcon
            iconDisplay="input"
            :manualInput="false"
            @update:modelValue="onRangeChange"
            class="w-full"
          />
        </div>
      </aside>

      <!-- RIGHT (Table + Slots) -->
      <section class="rounded-xl border bg-white p-4 overflow-hidden">
        <!-- ตาราง (อ่านอย่างเดียว) -->
        <DataTable
          :value="rows"
          :loading="loading"
          dataKey="id"
          :paginator="false"
          class="rounded-xl overflow-hidden"
          @row-click="onRowClick"
        >
          <template #empty>
            <div class="text-gray-500 py-8 text-center">
              ไม่มีข้อมูลในช่วงวันที่ที่เลือก
            </div>
          </template>

          <Column field="open_date" header="วันที่เปิด">
            <template #body="{ data }">
              <span class="font-medium cursor-pointer hover:underline">
                {{ formatThai(data.open_date) }}
              </span>
            </template>
          </Column>

          <Column field="note" header="หมายเหตุ">
            <template #body="{ data }">
              <span>{{ data.note || '-' }}</span>
            </template>
          </Column>
        </DataTable>

        <!-- Divider -->
        <div class="my-4 border-t"></div>

        <!-- Slots ของวันที่ที่เลือก -->
        <div v-if="selectedDay" class="space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">
              Slots — {{ formatThai(selectedDay.open_date) }}
            </h2>
            <Button label="รีโหลด Slots" icon="pi pi-refresh" text @click="loadSlots(selectedDay)" />
          </div>

          <div v-if="loadingSlots" class="text-gray-500">กำลังโหลด Slot...</div>
          <div v-else>
            <div v-if="slots.length === 0" class="text-gray-500">ยังไม่มี Slot ในวันนี้</div>

            <!-- การ์ด Slot -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="s in slots" :key="s.id" class="rounded-lg border p-4">
                <div class="flex items-center justify-between mb-2">
                  <div class="font-medium">
                    {{ fmtTime(s.start_time) }} - {{ fmtTime(s.end_time) }}
                  </div>
                  <Tag :severity="s.is_open ? 'success' : 'danger'" :value="s.is_open ? 'เปิด' : 'ปิด'" />
                </div>

                <!-- ตารางหัตถการใน slot -->
                <div class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead>
                      <tr class="text-left text-gray-600 border-b">
                        <th class="py-2 pr-3">หัตถการ</th>
                        <th class="py-2 pr-3">จองแล้ว</th>
                        <th class="py-2 pr-3">Max</th>
                        <th class="py-2 text-right">แก้ไข</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="p in s.procedures || []" :key="p.id" class="border-b last:border-0">
                        <td class="py-2 pr-3">{{ p.name }}</td>
                        <td class="py-2 pr-3">
                          <Tag :value="`${p.booked_count || 0}`" severity="info" />
                        </td>
                        <td class="py-2 pr-3">
                          <Tag :value="`${p.max ?? 0}`" severity="secondary" />
                        </td>
                        <td class="py-2 text-right">
                          <Button
                            icon="pi pi-pencil"
                            size="small"
                            text
                            @click="openEditMax(s, p)"
                            :disabled="saving"
                            :aria-label="`แก้ไข Max ของ ${p.name}`"
                          />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div> <!-- end card -->
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- Dialog: แก้ไข Max (แก้ที่ procedures.max) -->
    <Dialog v-model:visible="dlgEdit" modal header="แก้ไข Max การจอง" :style="{ width: '26rem' }">
      <div class="space-y-3">
        <div class="text-sm text-gray-600">
          วันที่: <b>{{ selectedDay ? formatThai(selectedDay.open_date) : '-' }}</b><br>
          เวลา: <b>{{ fmtTime(editCtx.start_time) }} - {{ fmtTime(editCtx.end_time) }}</b><br>
          หัตถการ: <b>{{ editCtx.procedure_name }}</b>
        </div>
        <div>
          <label class="block text-sm mb-1">จำนวน Max</label>
          <InputNumber v-model="editCtx.max" :min="0" :useGrouping="false" inputClass="w-full" />
        </div>
      </div>
      <template #footer>
        <Button label="ยกเลิก" text @click="dlgEdit=false" />
        <Button label="บันทึก" icon="pi pi-check" :loading="saving" @click="submitEditMax" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { parseISO, format } from 'date-fns'
import { th } from 'date-fns/locale'
import { request } from '@/lib/axiosService'

/* ===== โค้ดเดิม (ช่วงวันที่ & ตารางวัน) ===== */
const today = new Date()
const startDefault = new Date(today.getFullYear(), today.getMonth(), today.getDate())
const endDefault = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 30)

const range = ref([startDefault, endDefault])
const rows = ref([])
const loading = ref(false)

function ymd(d) {
  if (!(d instanceof Date)) return ''
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${dd}`
}
function formatThai(iso) {
  try {
    const d = parseISO(String(iso))
    return format(d, 'd MMMM yyyy', { locale: th })
  } catch { return iso }
}
async function load() {
  const [start, end] = range.value || []
  if (!(start instanceof Date) || !(end instanceof Date)) return
  loading.value = true
  try {
    const res = await request('get', `/api/admin/days-range?start=${encodeURIComponent(ymd(start))}&end=${encodeURIComponent(ymd(end))}`, {}, false)
    rows.value = res?.data?.data || []
  } finally {
    loading.value = false
  }
}
function onRangeChange() {
  const [s, e] = range.value || []
  if (s instanceof Date && e instanceof Date) load()
}
load()

/* ===== เพิ่มเติม: Slots & Edit Max ===== */
const selectedDay = ref(null)
const slots = ref([])
const loadingSlots = ref(false)

const dlgEdit = ref(false)
const saving = ref(false)
const editCtx = ref({
  time_slot_id: null,
  procedure_id: null,
  procedure_name: '',
  max: 0,
  start_time: '',
  end_time: ''
})

function fmtTime(t) {
  if (!t) return '-'
  const parts = String(t).split(':')
  return `${parts[0] ?? '00'}:${parts[1] ?? '00'}`
}

async function onRowClick(e) {
  const day = e?.data
  if (!day) return
  selectedDay.value = day
  await loadSlots(day)
}

async function loadSlots(day) {
  if (!day?.id) return
  loadingSlots.value = true
  try {
    // ดึง time_slots ของวัน + procedures(max) + booked_count
    const res = await request('get', `/api/admin/slots?open_day_id=${encodeURIComponent(day.id)}`, {}, false)
    slots.value = res?.data?.data || []
  } finally {
    loadingSlots.value = false
  }
}

function openEditMax(slot, proc) {
  editCtx.value = {
    time_slot_id: slot.id,
    procedure_id: proc.id,
    procedure_name: proc.name,
    max: Number(proc.max ?? 0),
    start_time: slot.start_time,
    end_time: slot.end_time
  }
  dlgEdit.value = true
}

async function submitEditMax() {
  try {
    saving.value = true
    const { procedure_id, max } = editCtx.value
    // ✅ แก้ที่ procedures.max
    await request('put', `/api/admin/procedures/${procedure_id}`, { max }, true)
    dlgEdit.value = false

    // อัปเดตค่าในจอทันที
    for (const s of slots.value) {
      const p = (s.procedures || []).find(x => x.id === procedure_id)
      if (p) p.max = Number(max)
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* ใช้ PrimeVue + ยูทิลิตี้ Tailwind กระจาย */
</style>
