<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopICD9OrthoSSSController extends BaseController
{
    public function view(Request $request)
    {
        return Inertia::render('Reports/TopICD9OrthoSSS', [
            'defaultStart' => now()->subDays(7)->toDateString(),
            'defaultEnd'   => now()->toDateString(),
        ]);
    }

    public function index(Request $request)
    {
        try {
            $start = $request->query('start');
            $end   = $request->query('end');
            $limit = (int) $request->query('limit', 5);
            $limit = max(1, min($limit, 50));

            if (!$start || !$end) {
                return $this->sendError('start/end is required', [], 422);
            }

            // สิทธิที่ต้องการ
            $pcodes   = ['AL','A7','A8'];
            $inPcodes = implode(',', array_fill(0, count($pcodes), '?'));

            // ชื่อที่แสดงของรหัส (ถ้าไม่มี master ให้คืนว่าง)
            $sql = "
                SELECT
                    t.icd9_code,
                    COALESCE(i9.name, '') AS icd9_name,
                    t.cases
                FROM (
                    SELECT
                        d.icd10 AS icd9_code,
                        COUNT(DISTINCT d.vn) AS cases
                    FROM ovst o
                    JOIN pttype p   ON p.pttype = o.pttype AND p.pcode IN ($inPcodes)
                    JOIN ovstdiag d ON d.vn = o.vn
                    WHERE o.vstdate BETWEEN ? AND ?
                      AND o.spclty = '08'              -- กระดูก
                      AND TRIM(d.icd10) REGEXP '^[0-9]' -- ICD-9 only
                    GROUP BY d.icd10
                    ORDER BY cases DESC
                    LIMIT ?
                ) t
                LEFT JOIN icd9cm i9 ON i9.code = t.icd9_code
                ORDER BY t.cases DESC
            ";

            $bindings = array_merge($pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);

            return $this->sendResponse($rows);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
