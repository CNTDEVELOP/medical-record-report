<?php
// app/Models/Booking.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id');
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function openDay()
    {
        return $this->belongsTo(OpenDay::class, 'open_day_id');
    }
}
