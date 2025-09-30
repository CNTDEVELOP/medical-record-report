<?php
// app/Http/Controllers/Admin/ProcedureController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Procedure;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController;

class ProcedureController extends BaseController
{
    /**
     * PUT /api/admin/procedures/{procedure}
     * body: { max: int>=0 }
     */
    public function updateMax(Procedure $procedure, Request $req)
    {
        $data = $req->validate([
            'max' => 'required|integer|min:0',
        ]);

        $procedure->max = $data['max'];
        $procedure->save();

        return $this->sendResponse([
            'id'   => (int)$procedure->id,
            'max'  => (int)$procedure->max,
        ], 'Updated');
    }
}
