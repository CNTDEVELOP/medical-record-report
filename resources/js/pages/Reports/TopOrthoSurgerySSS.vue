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

/* ---------- State ---------- */
const dateS = ref(props.defaultStart || new Date().toISOString().slice(0,10))
const dateE = ref(props.defaultEnd   || new Date().toISOString().slice(0,10))
const mode  = ref('all')      // 'opd' | 'ipd' | 'all'
const limit = ref(5)          // Top 5

/* limit clamp 1–50 */
const clampLimit = (v) => {
  const n = Number(v ?? 5)
  if (Number.isNaN(n)) return 5
  return Math.min(50, Math.max(1, Math.trunc(n)))
}
watch(limit, (v) => { limit.value = clampLimit(v) })

const isLoading = ref(false)
const items = ref([])

/* export filename: ORTHO_SSS_OPS_<MODE>_<YYYYMMDD_HHmmss>.csv */
const exportFile = computed(() => {
  const pad = (n) => String(n).padStart(2, '0')
  const d = new Date()
  const stamp = `${d.getFullYear()}${pad(d.getMonth()+1)}${pad(d.getDate())}_${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`
  const m = (mode.value || 'all').toUpperCase()
  return `ORTHO_SSS_OPS_${m}_${stamp}`
})

const dt = ref()
const exportCSV = () => dt.value?.exportCSV({ filename: exportFile.value })

/* utils */
const fmtDate = (v) => {
  if (!v) return ''
  if (typeof v === 'string') return v.slice(0,10)
  try { const d = new Date(v); return isNaN(d) ? '' : d.toISOString().slice(0,10) } catch { return '' }
}

/* fetch */
const fetchData = async () => {
  isLoading.value = true
  items.value = []
  try {
    const params = {
      start : fmtDate(dateS.value),
      end   : fmtDate(dateE.value),
      mode  : mode.value,
      limit : clampLimit(limit.value),
    }
    const { data } = await axios.get('/api/reports/top-ortho-surgery-sss', { params })
    if (data?.status) items.value = data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

/* init */
fetchData()
</script>

<template>
  <div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-xl sm:text-2xl font-semibold text-zinc-800 mb-4">
      หัตถการผ่าตัด 5 อันดับแรก — แผนกกระดูก (dep 080) — สิทธิ์ AL,A7,A8
    </h1>

    <!-- Controls -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
      <div class="flex flex-col">
        <label class="text-xs text-zinc-500 mb-1">วันที่เริ่ม (อิงวันผ่าตัด)</label>
        <Calendar v-model="dateS" dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" showIcon />
      </div>

      <div class="flex flex-col">
        <label class="text-xs text-zinc-500 mb-1">วันที่สิ้นสุด</label>
        <Calendar v-model="dateE" dateFormat="yy-mm-dd" class="w-full" inputClass="w-full" showIcon />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col">
          <label class="text-xs text-zinc-500 mb-1">โหมดอันดับ</label>
          <select v-model="mode" class="h-[42px] rounded-md border border-zinc-300 px-2 text-sm">
            <option value="all">รวม (OPD + IPD)</option>
            <option value="opd">เฉพาะ OPD</option>
            <option value="ipd">เฉพาะ IPD</option>
          </select>
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
        dataKey="op_name"
        responsiveLayout="scroll"
        ref="dt"
        :exportFilename="exportFile"
      >
        <!-- Header + Export -->
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
        <Column field="op_name" header="หัตถการ" exportHeader="หัตถการ" />

        <Column
          v-if="mode !== 'ipd'"
          field="opd_cases"
          header="OPD"
          style="width:120px"
          exportHeader="OPD"
          :body="row => row.opd_cases?.toLocaleString?.() ?? row.opd_cases"
        />
        <Column
          v-if="mode !== 'opd'"
          field="ipd_cases"
          header="IPD"
          style="width:120px"
          exportHeader="IPD"
          :body="row => row.ipd_cases?.toLocaleString?.() ?? row.ipd_cases"
        />
        <Column
          v-if="mode === 'all'"
          field="total_cases"
          header="รวม"
          style="width:120px"
          exportHeader="รวม"
          :body="row => row.total_cases?.toLocaleString?.() ?? row.total_cases"
        />
      </DataTable>
    </div>
  </div>
</template>
