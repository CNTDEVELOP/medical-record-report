<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopDiagnosisController extends BaseController
{
    /**
     * หน้า Inertia
     */
    public function view(Request $request)
    {
        return Inertia::render('Reports/TopDiagnosis', [
            // default date range: วันนี้ย้อนหลัง 7 วัน
            'defaultStart' => now()->subDays(7)->toDateString(),
            'defaultEnd'   => now()->toDateString(),
        ]);
    }

    /**
     * API: รวม OPD+IPD จัดอันดับ ICD10
     * GET /api/reports/top-diagnosis?start=YYYY-MM-DD&end=YYYY-MM-DD&limit=10&ipd_by=dchdate&pcode=AL
     */
    public function index(Request $request)
    {
        try {
            // --- รับพารามิเตอร์ ---
            $start = $request->query('start');
            $end   = $request->query('end');
            $limit = (int) $request->query('limit', 10);
            $limit = max(1, min($limit, 100)); // กัน limit โหดเกิน

            // IPD จะใช้ dchdate เป็นค่า default (ตามที่คุณย้ำมา)
            $ipdBy = $request->query('ipd_by', 'dchdate'); // 'dchdate' หรือ 'regdate'
            $ipdDateCol = $ipdBy === 'regdate' ? 'ip.regdate' : 'ip.dchdate';

            // ตัวเลือกสิทธิ (เช่น pcode=AL) ถ้าอยากกรอง
            $pcode = $request->query('pcode'); // ตัวอย่าง 'AL' หรือ null เพื่อไม่กรอง

            // --- validate เบื้องต้น ---
            if (!$start || !$end) {
                return $this->sendError('start/end is required', [], 422);
            }

            // --- สร้าง SQL แบบ raw + binding อย่างปลอดภัย ---
            $opdJoinPttype  = '';
            $ipdJoinPttype  = '';
            $bindings = [];

            if ($pcode) {
                $opdJoinPttype = 'JOIN pttype p ON p.pttype = o.pttype AND p.pcode = ?';
                $bindings[] = $pcode;
            }
            // ช่วงวันที่ OPD
            $bindings[] = $start;
            $bindings[] = $end;

            if ($pcode) {
                $ipdJoinPttype = 'JOIN pttype p2 ON p2.pttype = ip.pttype AND p2.pcode = ?';
                $bindings[] = $pcode;
            }
            // ช่วงวันที่ IPD
            $bindings[] = $start;
            $bindings[] = $end;

            // limit
            $bindings[] = $limit;

            $sql = "
                SELECT 
                    t.icd10,
                    COALESCE(i.name, '') AS icd10_name_th,
                    SUM(t.opd_cases) AS opd_cases,
                    SUM(t.ipd_cases) AS ipd_cases,
                    (SUM(t.opd_cases) + SUM(t.ipd_cases)) AS total_cases
                FROM (
                    -- OPD: นับตาม vstdate และตัดเคสที่มี an ออก
                    SELECT 
                        d.icd10,
                        COUNT(DISTINCT d.vn) AS opd_cases,
                        0 AS ipd_cases
                    FROM ovstdiag d
                    JOIN ovst o ON o.vn = d.vn
                    {$opdJoinPttype}
                    WHERE TRIM(d.diagtype) = '1'
                      AND o.vstdate BETWEEN ? AND ?
                      AND o.an IS NULL
                    GROUP BY d.icd10

                    UNION ALL

                    -- IPD: นับตาม {$ipdDateCol}
                    SELECT
                        d.icd10,
                        0 AS opd_cases,
                        COUNT(DISTINCT d.an) AS ipd_cases
                    FROM iptdiag d
                    JOIN ipt ip ON ip.an = d.an
                    {$ipdJoinPttype}
                    WHERE TRIM(d.diagtype) = '1'
                      AND {$ipdDateCol} BETWEEN ? AND ?
                    GROUP BY d.icd10
                ) AS t
                LEFT JOIN icd101 i ON i.code = t.icd10
                GROUP BY t.icd10, icd10_name_th
                ORDER BY total_cases DESC
                LIMIT ?
            ";

            // --- ยิงไปฐาน 172.199.9.252 ---
            $rows = DB::connection('mysql_252')->select($sql, $bindings);

            return $this->sendResponse($rows);
        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
