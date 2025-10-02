<script setup>
import { ref, watch, computed } from 'vue'
import axios from 'axios'
import Calendar from 'primevue/calendar'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const props = defineProps({
  defaultStart: String,
  defaultEnd: String,
})

const dateS = ref(props.defaultStart || new Date().toISOString().slice(0,10))
const dateE = ref(props.defaultEnd   || new Date().toISOString().slice(0,10))
const limit  = ref(5)

const clampLimit = (v) => {
  const n = Number(v ?? 5)
  if (Number.isNaN(n)) return 5
  return Math.min(50, Math.max(1, Math.trunc(n)))
}
watch(limit, (v) => { limit.value = clampLimit(v) })

const isLoading = ref(false)
const items = ref([])

/* export filename */
const exportFile = computed(() => {
  const pad = (n) => String(n).padStart(2, '0')
  const d = new Date()
  const stamp = `${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
  return `ICD9_ORTHO_ALA7A8_${stamp}`
})
const dt = ref()
const exportCSV = () => dt.value?.exportCSV({ filename: exportFile.value })

const fmtDate = (v) => {
  if (!v) return ''
  if (typeof v === 'string') return v.slice(0,10)
  try { const d = new Date(v); return isNaN(d) ? '' : d.toISOString().slice(0,10) } catch { return '' }
}

const fetchData = async () => {
  isLoading.value = true
  items.value = []
  try {
    const params = {
      start : fmtDate(dateS.value),
      end   : fmtDate(dateE.value),
      limit : clampLimit(limit.value),
    }
    const { data } = await axios.get('/api/reports/top-icd9-ortho-sss', { params })
    if (data?.status) items.value = data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

fetchData()
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-xl sm:text-2xl font-semibold text-zinc-800 mb-4">
      ICD-9 หัตถการยอดนิยม 5 อันดับ — แผนกกระดูก (spclty=08) — สิทธิ AL,A7,A8
    </h1>

    <!-- Controls -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
      <div class="flex flex-col">
        <label class="text-xs text-zinc-500 mb-1">วันที่เริ่ม (vstdate)</label>
        <Calendar v-model="dateS" dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" showIcon />
      </div>

      <div class="flex flex-col">
        <label class="text-xs text-zinc-500 mb-1">วันที่สิ้นสุด</label>
        <Calendar v-model="dateE" dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" showIcon />
      </div>

      <div class="flex flex-col">
        <label class="text-xs text-zinc-500 mb-1">Top N (limit)</label>
        <input
          type="number"
          min="1"
          max="50"
          v-model.number="limit"
          @input="limit = clampLimit(limit)"
          class="h-[42px] rounded-md border border-zinc-300 px-2 text-sm"
        />
        <small class="text-[11px] text-zinc-500 mt-1">ค่าระหว่าง 1–50</small>
      </div>

      <div class="flex items-end">
        <Button :label="isLoading ? 'กำลังค้น...' : 'ค้นหา'"
                :disabled="isLoading"
                class="w-full md:w-auto"
                @click="fetchData" />
      </div>
    </div>

    <!-- Result -->
    <div class="rounded-xl border bg-white p-3">
      <DataTable
        :value="items"
        :loading="isLoading"
        dataKey="icd9_code"
        responsiveLayout="scroll"
        ref="dt"
        :exportFilename="exportFile"
      >
        <template #header>
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="font-medium">Export</div>
              <div class="text-[12px] text-zinc-500">DataTable can export its data to CSV format.</div>
            </div>
            <Button icon="pi pi-external-link" label="Export CSV" @click="exportCSV" />
          </div>
        </template>

        <Column header="#" :body="(_, opt) => opt.rowIndex + 1" style="width:70px" :exportable="false" />
        <Column field="icd9_code" header="ICD-9" exportHeader="ICD-9" />
        <Column field="icd9_name" header="ชื่อหัตถการ (ไทย)" exportHeader="ชื่อหัตถการ (ไทย)" />
        <Column field="cases" header="จำนวนครั้ง" style="width:140px" exportHeader="จำนวนครั้ง"
                :body="row => row.cases?.toLocaleString?.() ?? row.cases" />
      </DataTable>
    </div>
  </div>
</template>
