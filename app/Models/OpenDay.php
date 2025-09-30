<?php
// app/Models/OpenDay.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpenDay extends Model
{
    protected $table = 'open_days';
    protected $fillable = ['open_date', 'note'];

    public function timeSlots()
    {
        return $this->hasMany(TimeSlot::class, 'open_day_id');
    }
}
