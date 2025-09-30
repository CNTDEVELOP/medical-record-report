<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OpenDay;
use App\Models\TimeSlot;
use App\Models\SlotProcedure;
use App\Models\Procedure;
use App\Http\Controllers\API\BaseController;


class ManageScheduleController extends BaseController
{
    public function listDaysByRange(Request $req) {
        $data = $req->validate([
            'start' => 'required|date_format:Y-m-d',
            'end'   => 'required|date_format:Y-m-d|after_or_equal:start',
        ]);
    
        $rows = OpenDay::query()
            ->whereBetween('open_date', [$data['start'], $data['end']])
            ->orderBy('open_date')
            ->get(['id','open_date','note']);
    
        return $this->sendResponse($rows); // status=true, data=[...]
    }
    
    // ---- Days ----
    public function listDays(Request $req) {
        $month = $req->query('month') ?? now('Asia/Bangkok')->format('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['message'=>'month=YYYY-MM'], 422);
        }
    
        [$y,$m] = explode('-', $month);
        $start = sprintf('%04d-%02d-01', $y, $m);
        $end   = date('Y-m-d', strtotime("$start +1 month"));
    
        $perPage = (int)($req->query('per_page', 10));
        $perPage = $perPage > 0 ? $perPage : 10;
    
        $rows = OpenDay::query()
            ->whereBetween('open_date', [$start, $end])
            ->orderBy('open_date')
            ->paginate($perPage, ['id','open_date','note']);
    
        return response()->json($rows);
    }

    public function createDay(Request $req) {
        $data = $req->validate([
            'open_date' => 'required|date_format:Y-m-d|unique:open_days,open_date',
            'note'      => 'nullable|string|max:255',
        ]);
        $d = OpenDay::create($data);
        return response()->json($d, 201);
    }

    public function updateDay($id, Request $req) {
        $data = $req->validate([
            'open_date' => 'nullable|date_format:Y-m-d|unique:open_days,open_date,'.$id,
            'note'      => 'nullable|string|max:255',
        ]);
        $d = OpenDay::findOrFail($id);
        $d->fill(array_filter($data, fn($v)=>!is_null($v)));
        $d->save();
        return response()->json(['message'=>'updated']);
    }

    public function deleteDay($id) {
        // ลบวัน => cascade ลบ slots + slot_procedures
        $d = OpenDay::findOrFail($id);
        $d->delete();
        return response()->json(['message'=>'deleted']);
    }

    // ---- Slots ----
    public function listSlots($dayId) {
        $slots = TimeSlot::query()
            ->where('open_day_id', $dayId)
            ->orderBy('start_time')
            ->get(['id','open_day_id','start_time','end_time','is_open']);

        return response()->json($slots);
    }

    public function createSlot($dayId, Request $req) {
        $data = $req->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'is_open'    => 'nullable|boolean',
        ]);

        // ป้องกันชนกันซ้อน (ง่าย ๆ: ไม่ให้ซ้ำ start+end)
        $exists = TimeSlot::query()
            ->where('open_day_id',$dayId)
            ->where('start_time',$data['start_time'])
            ->where('end_time',$data['end_time'])
            ->exists();
        if ($exists) return response()->json(['message'=>'slot exists'], 409);

        $slot = TimeSlot::create([
            'open_day_id' => $dayId,
            'start_time'  => $data['start_time'],
            'end_time'    => $data['end_time'],
            'is_open'     => $data['is_open'] ?? true,
        ]);
        return response()->json($slot, 201);
    }

    public function updateSlot($id, Request $req) {
        $data = $req->validate([
            'start_time' => 'nullable|date_format:H:i',
            'end_time'   => 'nullable|date_format:H:i',
            'is_open'    => 'nullable|boolean',
        ]);
        $slot = TimeSlot::findOrFail($id);
        if (isset($data['start_time'])) $slot->start_time = $data['start_time'];
        if (isset($data['end_time']))   $slot->end_time   = $data['end_time'];
        if (isset($data['is_open']))    $slot->is_open    = $data['is_open'];
        if ($slot->end_time <= $slot->start_time) return response()->json(['message'=>'end_time must be after start_time'], 422);
        $slot->save();
        return response()->json(['message'=>'updated']);
    }

    public function deleteSlot($id) {
        $slot = TimeSlot::findOrFail($id);
        $slot->delete(); // จะ cascade ลบ slot_procedures
        return response()->json(['message'=>'deleted']);
    }

    // ---- Slot × Procedure (capacity per procedure per slot) ----
    public function listSlotProcedures($slotId) {
        $rows = DB::table('slot_procedures as sp')
            ->join('procedures as p','p.id','=','sp.procedure_id')
            ->where('sp.time_slot_id',$slotId)
            ->select('sp.id','sp.time_slot_id','sp.procedure_id','p.name as procedure_name','sp.capacity_max')
            ->orderBy('p.name')
            ->get();
        return response()->json($rows);
    }

    public function upsertSlotProcedure($slotId, Request $req) {
        $data = $req->validate([
            'procedure_id' => 'required|integer|exists:procedures,id',
            'capacity_max' => 'required|integer|min:0',
        ]);

        $sp = SlotProcedure::query()
            ->firstOrNew(['time_slot_id'=>$slotId,'procedure_id'=>$data['procedure_id']]);
        $sp->capacity_max = $data['capacity_max'];
        $sp->save();

        return response()->json(['message'=>$sp->wasRecentlyCreated?'created':'updated','id'=>$sp->id], $sp->wasRecentlyCreated?201:200);
    }

    public function deleteSlotProcedure($id) {
        SlotProcedure::findOrFail($id)->delete();
        return response()->json(['message'=>'deleted']);
    }

    // สำหรับ dropdown
    public function allProcedures() {
        return Procedure::query()->where('active',1)->orderBy('name')->get(['id','name']);
    }
}
