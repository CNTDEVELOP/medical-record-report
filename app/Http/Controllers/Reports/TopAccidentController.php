<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopAccidentController extends BaseController
{
    public function view(Request $request)
    {
        return Inertia::render('Reports/TopAccident', [
            'defaultStart' => now()->subDays(7)->toDateString(),
            'defaultEnd'   => now()->toDateString(),
        ]);
    }

    public function index(Request $request)
    {
        try {
            $start = $request->query('start');
            $end   = $request->query('end');
            $mode  = strtolower($request->query('mode', 'all')); // all|opd|ipd
            $pcode = $request->query('pcode', 'AL');             // default AL
            $limit = (int) $request->query('limit', 5);
            $limit = max(1, min($limit, 50));

            // IPD ใช้ dchdate เป็นค่า default
            $ipdBy = $request->query('ipd_by', 'dchdate'); // dchdate|regdate
            $ipdDateCol = $ipdBy === 'regdate' ? 'ip.regdate' : 'ip.dchdate';

            if (!$start || !$end) {
                return $this->sendError('start/end is required', [], 422);
            }

            // เงื่อนไขเลือกเฉพาะ ICD-10 กลุ่ม S00-S99
            $injuryFilter = "LEFT(UPPER(d.icd10),1)='S' AND CAST(SUBSTRING(UPPER(d.icd10),2,2) AS UNSIGNED) BETWEEN 0 AND 99";

            // ====== สร้าง SQL ตาม mode ======
            if ($mode === 'opd') {
                $sql = "
                    SELECT 
                        t.icd10,
                        COALESCE(i.name,'') AS icd10_name_th,
                        t.cases AS opd_cases
                    FROM (
                        SELECT d.icd10, COUNT(DISTINCT d.vn) AS cases
                        FROM ovstdiag d
                        JOIN ovst o   ON o.vn = d.vn
                        JOIN pttype p ON p.pttype = o.pttype AND p.pcode = ?
                        WHERE TRIM(d.diagtype)='1'
                          AND o.vstdate BETWEEN ? AND ?
                          AND o.an IS NULL
                          AND {$injuryFilter}
                        GROUP BY d.icd10
                    ) t
                    LEFT JOIN icd101 i ON i.code = t.icd10
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $rows = DB::connection('mysql_252')->select($sql, [$pcode, $start, $end, $limit]);
                return $this->sendResponse($rows);
            }

            if ($mode === 'ipd') {
                $sql = "
                    SELECT
                        t.icd10,
                        COALESCE(i.name,'') AS icd10_name_th,
                        t.cases AS ipd_cases
                    FROM (
                        SELECT d.icd10, COUNT(DISTINCT d.an) AS cases
                        FROM iptdiag d
                        JOIN ipt ip   ON ip.an = d.an
                        JOIN pttype p ON p.pttype = ip.pttype AND p.pcode = ?
                        WHERE TRIM(d.diagtype)='1'
                          AND {$ipdDateCol} BETWEEN ? AND ?
                          AND {$injuryFilter}
                        GROUP BY d.icd10
                    ) t
                    LEFT JOIN icd101 i ON i.code = t.icd10
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $rows = DB::connection('mysql_252')->select($sql, [$pcode, $start, $end, $limit]);
                return $this->sendResponse($rows);
            }

            // mode === 'all' (รวม OPD + IPD)
            $sql = "
                SELECT 
                    x.icd10,
                    COALESCE(i.name,'') AS icd10_name_th,
                    SUM(x.opd_cases) AS opd_cases,
                    SUM(x.ipd_cases) AS ipd_cases,
                    (SUM(x.opd_cases) + SUM(x.ipd_cases)) AS total_cases
                FROM (
                    SELECT d.icd10, COUNT(DISTINCT d.vn) AS opd_cases, 0 AS ipd_cases
                    FROM ovstdiag d
                    JOIN ovst o   ON o.vn = d.vn
                    JOIN pttype p ON p.pttype = o.pttype AND p.pcode = ?
                    WHERE TRIM(d.diagtype)='1'
                      AND o.vstdate BETWEEN ? AND ?
                      AND o.an IS NULL
                      AND {$injuryFilter}
                    GROUP BY d.icd10

                    UNION ALL

                    SELECT d.icd10, 0 AS opd_cases, COUNT(DISTINCT d.an) AS ipd_cases
                    FROM iptdiag d
                    JOIN ipt ip   ON ip.an = d.an
                    JOIN pttype p ON p.pttype = ip.pttype AND p.pcode = ?
                    WHERE TRIM(d.diagtype)='1'
                      AND {$ipdDateCol} BETWEEN ? AND ?
                      AND {$injuryFilter}
                    GROUP BY d.icd10
                ) x
                LEFT JOIN icd101 i ON i.code = x.icd10
                GROUP BY x.icd10, icd10_name_th
                ORDER BY total_cases DESC
                LIMIT ?
            ";
            $rows = DB::connection('mysql_252')->select($sql, [$pcode, $start, $end, $pcode, $start, $end, $limit]);
            return $this->sendResponse($rows);

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
