<?php
// app/Http/Controllers/Admin/TimeSlotController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\BaseController;

class TimeSlotController extends BaseController
{
    /**
     * ดึง slots ของวัน + procedures (ชื่อ, max, active) + booked_count (นับจาก bookings)
     * GET /api/admin/slots?open_day_id=3
     *
     * response:
     * [
     *   {
     *     "id": 1,
     *     "start_time": "09:00:00",
     *     "end_time": "10:00:00",
     *     "is_open": 1,
     *     "procedures": [
     *       { "id": 2, "name": "ขูดหินปูน", "max": 4, "active": 1, "booked_count": 3 },
     *       ...
     *     ]
     *   }, ...
     * ]
     */
    public function index(Request $req)
    {
        $openDayId = $req->query('open_day_id');
        if (!$openDayId) {
            return $this->sendError('open_day_id is required', [], 422);
        }

        // 1) slot ของวัน
        $slots = TimeSlot::query()
            ->where('open_day_id', $openDayId)
            ->orderBy('start_time')
            ->get(['id','start_time','end_time','is_open']);

        if ($slots->isEmpty()) {
            return $this->sendResponse([]);
        }

        $slotIds = $slots->pluck('id')->all();

        // 2) join slot_procedures -> procedures เพื่อเอา name, max, active
        $procs = DB::table('slot_procedures as sp')
            ->join('procedures as p', 'p.id', '=', 'sp.procedure_id')
            ->whereIn('sp.time_slot_id', $slotIds)
            ->select(
                'sp.time_slot_id',
                'p.id as procedure_id',
                'p.name',
                'p.max',
                'p.active'
            )
            ->get();

        // 3) นับ bookings ต่อ (time_slot_id, procedure_id)
        //    ปรับสถานะที่นับเป็น “จองแล้ว” ให้ตรงระบบคุณ (ตัวอย่างนี้นับ confirmed/paid/completed)
        $booked = DB::table('bookings')
            ->whereIn('time_slot_id', $slotIds)
            ->whereIn('status', ['confirmed','paid','completed'])
            ->select('time_slot_id','procedure_id', DB::raw('COUNT(*) as booked_count'))
            ->groupBy('time_slot_id','procedure_id')
            ->get();

        $countMap = [];
        foreach ($booked as $b) {
            $countMap[$b->time_slot_id.'_'.$b->procedure_id] = (int)$b->booked_count;
        }

        // 4) รวม procedures ต่อ slot
        $procBySlot = [];
        foreach ($procs as $r) {
            $key = $r->time_slot_id.'_'.$r->procedure_id;
            $procBySlot[$r->time_slot_id][] = [
                'id'           => (int)$r->procedure_id,
                'name'         => $r->name,
                'max'          => (int)$r->max,
                'active'       => (int)$r->active,
                'booked_count' => $countMap[$key] ?? 0,
            ];
        }

        // 5) สร้าง output
        $out = $slots->map(function ($s) use ($procBySlot) {
            return [
                'id'         => (int)$s->id,
                'start_time' => $s->start_time,
                'end_time'   => $s->end_time,
                'is_open'    => (int)$s->is_open,
                'procedures' => $procBySlot[$s->id] ?? [],
            ];
        })->values();

        return $this->sendResponse($out);
    }
}
