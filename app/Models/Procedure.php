<?php
// app/Models/Procedure.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    protected $table = 'procedures';
    protected $fillable = ['name', 'max', 'active'];

    public function slotProcedures()
    {
        return $this->hasMany(SlotProcedure::class, 'procedure_id');
    }
}