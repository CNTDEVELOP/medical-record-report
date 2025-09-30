<?php
// app/Models/SlotProcedure.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotProcedure extends Model
{
    protected $table = 'slot_procedures';
    protected $fillable = ['time_slot_id', 'procedure_id'];

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }
}
