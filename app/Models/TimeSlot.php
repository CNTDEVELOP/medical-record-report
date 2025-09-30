<?php
// app/Models/TimeSlot.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $table = 'time_slots';
    protected $fillable = ['open_day_id', 'start_time', 'end_time', 'is_open'];

    public function openDay()
    {
        return $this->belongsTo(OpenDay::class, 'open_day_id');
    }

    public function slotProcedures()
    {
        return $this->hasMany(SlotProcedure::class, 'time_slot_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'time_slot_id');
    }
}
