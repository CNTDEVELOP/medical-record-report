<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopOrthoSurgerySSSController extends BaseController
{
    public function view(Request $request)
    {
        return Inertia::render('Reports/TopOrthoSurgerySSS', [
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
        $pcodes = ['AL','A7','A8'];
        $inPcode = implode(',', array_fill(0, count($pcodes), '?')); // ?,?,?

        /* -------------------- OPD ONLY -------------------- */
        if ($mode === 'opd') {
            $sql = "
                SELECT
                    t.icd9_code,
                    COALESCE(i9.name,'') AS icd9_name,
                    t.cases AS opd_cases
                FROM (
                    SELECT
                        d.icd10 AS icd9_code,
                        COUNT(DISTINCT o.vn) AS cases
                    FROM ovst o
                    JOIN pttype p     ON p.pttype = o.pttype AND p.pcode IN ($inPcode)
                    JOIN opddiag d    ON d.vn = o.vn
                    JOIN operation_list ol ON ol.vn = o.vn
                    WHERE ol.operation_date BETWEEN ? AND ?
                      AND o.spclty = '08'                          -- แผนกกระดูก (OPD)
                      AND TRIM(d.icd10) REGEXP '^[0-9]'            -- เอาเฉพาะ ICD-9
                      AND TRIM(d.icd10) <> ''
                    GROUP BY d.icd10
                ) t
                LEFT JOIN icd9cm i9 ON i9.code = t.icd9_code
                ORDER BY t.cases DESC
                LIMIT ?
            ";
            $bindings = array_merge($pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);
        }

        /* -------------------- IPD ONLY -------------------- */
        if ($mode === 'ipd') {
            $sql = "
                SELECT
                    t.icd9_code,
                    COALESCE(i9.name,'') AS icd9_name,
                    t.cases AS ipd_cases
                FROM (
                    SELECT
                        pr.icd9 AS icd9_code,
                        COUNT(DISTINCT ip.an) AS cases
                    FROM iptoprt pr
                    JOIN ipt ip        ON ip.an = pr.an
                    JOIN pttype p      ON p.pttype = ip.pttype AND p.pcode IN ($inPcode)
                    JOIN operation_list ol ON ol.an = ip.an
                    WHERE ol.operation_date BETWEEN ? AND ?
                      AND ol.send_depcode = '080'                   -- กระดูก (IPD)
                      AND TRIM(pr.icd9) REGEXP '^[0-9]'             -- เอาเฉพาะ ICD-9
                      AND TRIM(pr.icd9) <> ''
                    GROUP BY pr.icd9
                ) t
                LEFT JOIN icd9cm i9 ON i9.code = t.icd9_code
                ORDER BY t.cases DESC
                LIMIT ?
            ";
            $bindings = array_merge($pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);
        }

        /* -------------------- ALL (OPD + IPD) -------------------- */
        $sql = "
            SELECT
                x.icd9_code,
                COALESCE(i9.name,'') AS icd9_name,
                SUM(x.opd_cases) AS opd_cases,
                SUM(x.ipd_cases) AS ipd_cases,
                (SUM(x.opd_cases) + SUM(x.ipd_cases)) AS total_cases
            FROM (
                /* OPD */
                SELECT
                    d.icd10 AS icd9_code,
                    COUNT(DISTINCT o.vn) AS opd_cases,
                    0 AS ipd_cases
                FROM ovst o
                JOIN pttype p     ON p.pttype = o.pttype AND p.pcode IN ($inPcode)
                JOIN opddiag d    ON d.vn = o.vn
                JOIN operation_list ol ON ol.vn = o.vn
                WHERE ol.operation_date BETWEEN ? AND ?
                  AND o.spclty = '08'
                  AND TRIM(d.icd10) REGEXP '^[0-9]'
                  AND TRIM(d.icd10) <> ''
                GROUP BY d.icd10

                UNION ALL

                /* IPD */
                SELECT
                    pr.icd9 AS icd9_code,
                    0 AS opd_cases,
                    COUNT(DISTINCT ip.an) AS ipd_cases
                FROM iptoprt pr
                JOIN ipt ip        ON ip.an = pr.an
                JOIN pttype p2     ON p2.pttype = ip.pttype AND p2.pcode IN ($inPcode)
                JOIN operation_list ol2 ON ol2.an = ip.an
                WHERE ol2.operation_date BETWEEN ? AND ?
                  AND ol2.send_depcode = '080'
                  AND TRIM(pr.icd9) REGEXP '^[0-9]'
                  AND TRIM(pr.icd9) <> ''
                GROUP BY pr.icd9
            ) x
            LEFT JOIN icd9cm i9 ON i9.code = x.icd9_code
            GROUP BY x.icd9_code, icd9_name
            ORDER BY total_cases DESC
            LIMIT ?
        ";
        $bindings = array_merge(
            $pcodes, [$start, $end],   // OPD
            $pcodes, [$start, $end],   // IPD
            [$limit]
        );
        $rows = DB::connection('mysql_252')->select($sql, $bindings);
        return $this->sendResponse($rows);

    } catch (\Throwable $e) {
        return $this->sendError($e->getMessage());
    }
}

}
