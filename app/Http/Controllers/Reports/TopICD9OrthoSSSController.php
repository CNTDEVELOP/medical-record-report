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
        $mode  = strtolower($request->query('mode', 'all')); // all|opd|ipd
        $limit = (int) $request->query('limit', 5);
        $limit = max(1, min($limit, 50));

        if (!$start || !$end) {
            return $this->sendError('start/end is required', [], 422);
        }

        // สิทธิที่ต้องการ
        $pcodes  = ['AL','A7','A8'];
        $inCodes = implode(',', array_fill(0, count($pcodes), '?')); // ?,?,?

        /* -------------------- OPD ONLY (ICD-10 + มีผ่าตัดจริง) -------------------- */
        if ($mode === 'opd') {
            $sql = "
                SELECT
                    t.icd10_code,
                    COALESCE(i.name,'') AS icd10_name_th,
                    t.cases
                FROM (
                    SELECT
                        d.icd10 AS icd10_code,
                        COUNT(DISTINCT o.vn) AS cases
                    FROM ovst o
                    JOIN pttype p          ON p.pttype = o.pttype AND p.pcode IN ($inCodes)
                    JOIN ovstdiag d        ON d.vn = o.vn
                    JOIN operation_list ol ON ol.vn = o.vn
                    WHERE ol.operation_date BETWEEN ? AND ?
                      AND o.spclty = '08'                            -- แผนกกระดูก (OPD)
                      AND TRIM(d.icd10) REGEXP '^[A-Za-z]'          -- ICD-10 เท่านั้น
                      AND TRIM(d.icd10) <> ''
                      AND TRIM(d.diagtype) = '1'                    -- Dx หลัก (ลบออกได้ถ้าไม่มีในฐาน)
                    GROUP BY d.icd10
                    ORDER BY cases DESC
                    LIMIT ?
                ) t
                LEFT JOIN icd101 i ON i.code = t.icd10_code
                ORDER BY t.cases DESC
            ";
            $bindings = array_merge($pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);
        }

        /* -------------------- IPD ONLY (ICD-10 + มีผ่าตัดจริง) -------------------- */
        if ($mode === 'ipd') {
            $sql = "
                SELECT
                    t.icd10_code,
                    COALESCE(i.name,'') AS icd10_name_th,
                    t.cases
                FROM (
                    SELECT
                        d.icd10 AS icd10_code,
                        COUNT(DISTINCT ip.an) AS cases
                    FROM iptdiag d
                    JOIN ipt ip           ON ip.an = d.an
                    JOIN pttype p         ON p.pttype = ip.pttype AND p.pcode IN ($inCodes)
                    JOIN operation_list ol ON ol.an = ip.an
                    WHERE ol.operation_date BETWEEN ? AND ?
                      AND ol.send_depcode = '080'                     -- กระดูก (OR ส่งเข้ากระดูก)
                      AND TRIM(d.icd10) REGEXP '^[A-Za-z]'            -- ICD-10
                      AND TRIM(d.icd10) <> ''
                      AND TRIM(d.diagtype) = '1'                      -- Dx หลัก
                    GROUP BY d.icd10
                    ORDER BY cases DESC
                    LIMIT ?
                ) t
                LEFT JOIN icd101 i ON i.code = t.icd10_code
                ORDER BY t.cases DESC
            ";
            $bindings = array_merge($pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);
        }

        /* -------------------- ALL (OPD + IPD) ICD-10 + มีผ่าตัดจริง -------------------- */
        $sql = "
            SELECT
                x.icd10_code,
                COALESCE(i.name,'') AS icd10_name_th,
                (SUM(x.opd_cases) + SUM(x.ipd_cases)) AS cases
            FROM (
                /* OPD */
                SELECT
                    d.icd10 AS icd10_code,
                    COUNT(DISTINCT o.vn) AS opd_cases,
                    0 AS ipd_cases
                FROM ovst o
                JOIN pttype p          ON p.pttype = o.pttype AND p.pcode IN ($inCodes)
                JOIN ovstdiag d        ON d.vn = o.vn
                JOIN operation_list ol ON ol.vn = o.vn
                WHERE ol.operation_date BETWEEN ? AND ?
                  AND o.spclty = '08'
                  AND TRIM(d.icd10) REGEXP '^[A-Za-z]'
                  AND TRIM(d.icd10) <> ''
                  AND TRIM(d.diagtype) = '1'
                GROUP BY d.icd10

                UNION ALL

                /* IPD */
                SELECT
                    d.icd10 AS icd10_code,
                    0 AS opd_cases,
                    COUNT(DISTINCT ip.an) AS ipd_cases
                FROM iptdiag d
                JOIN ipt ip            ON ip.an = d.an
                JOIN pttype p2         ON p2.pttype = ip.pttype AND p2.pcode IN ($inCodes)
                JOIN operation_list ol2 ON ol2.an = ip.an
                WHERE ol2.operation_date BETWEEN ? AND ?
                  AND ol2.send_depcode = '080'
                  AND TRIM(d.icd10) REGEXP '^[A-Za-z]'
                  AND TRIM(d.icd10) <> ''
                  AND TRIM(d.diagtype) = '1'
                GROUP BY d.icd10
            ) x
            LEFT JOIN icd101 i ON i.code = x.icd10_code
            GROUP BY x.icd10_code, icd10_name_th
            ORDER BY cases DESC
            LIMIT ?
        ";
        $bindings = array_merge(
            $pcodes, [$start, $end],   // สำหรับซับคิวรี OPD
            $pcodes, [$start, $end],   // สำหรับซับคิวรี IPD
            [$limit]
        );
        $rows = DB::connection('mysql_252')->select($sql, $bindings);
        return $this->sendResponse($rows);

    } catch (\Throwable $e) {
        return $this->sendError($e->getMessage());
    }
}
}
