<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\API\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TopAccidentSSSController extends BaseController
{
    public function view(Request $request)
    {
        return Inertia::render('Reports/TopAccidentSSS', [
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

            // IPD อ้างวันที่ตาม dchdate เป็นค่าเริ่มต้น (เปลี่ยนได้เป็น regdate)
            $ipdBy = $request->query('ipd_by', 'dchdate'); // dchdate|regdate
            $ipdDateCol = $ipdBy === 'regdate' ? 'ip.regdate' : 'ip.dchdate';

            if (!$start || !$end) {
                return $this->sendError('start/end is required', [], 422);
            }

            // เงื่อนไขกลุ่มอุบัติเหตุ: S00–S99 (ใช้ 3 ตัวแรกกันเคสมีทศนิยม S06.5)
            $injuryFilter = "SUBSTR(UPPER(d.icd10),1,3) BETWEEN 'S00' AND 'S99'";

            // pcode ของ SSS (ตรึงเป็น A7,A8)
            $pcodes = ['A7', 'A8'];
            $inPlace = implode(',', array_fill(0, count($pcodes), '?'));
            $opdJoin = "JOIN pttype p ON p.pttype = o.pttype AND p.pcode IN ($inPlace)";
            $ipdJoin = "JOIN pttype p2 ON p2.pttype = ip.pttype AND p2.pcode IN ($inPlace)";

            if ($mode === 'opd') {
                $sql = "
                    SELECT 
                        t.icd10,
                        COALESCE(i.name,'') AS icd10_name_th,
                        t.cases AS opd_cases
                    FROM (
                        SELECT d.icd10, COUNT(DISTINCT d.vn) AS cases
                        FROM ovstdiag d
                        JOIN ovst o ON o.vn = d.vn
                        $opdJoin
                        WHERE TRIM(d.diagtype)='1'
                          AND o.vstdate BETWEEN ? AND ?
                          AND o.an IS NULL
                          AND $injuryFilter
                        GROUP BY d.icd10
                    ) t
                    LEFT JOIN icd101 i ON i.code = t.icd10
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $bindings = array_merge($pcodes, [$start, $end, $limit]);
                $rows = DB::connection('mysql_252')->select($sql, $bindings);
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
                        JOIN ipt ip ON ip.an = d.an
                        $ipdJoin
                        WHERE TRIM(d.diagtype)='1'
                          AND $ipdDateCol BETWEEN ? AND ?
                          AND $injuryFilter
                        GROUP BY d.icd10
                    ) t
                    LEFT JOIN icd101 i ON i.code = t.icd10
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $bindings = array_merge($pcodes, [$start, $end, $limit]);
                $rows = DB::connection('mysql_252')->select($sql, $bindings);
                return $this->sendResponse($rows);
            }

            // mode === 'all' รวม OPD + IPD
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
                    JOIN ovst o ON o.vn = d.vn
                    $opdJoin
                    WHERE TRIM(d.diagtype)='1'
                      AND o.vstdate BETWEEN ? AND ?
                      AND o.an IS NULL
                      AND $injuryFilter
                    GROUP BY d.icd10

                    UNION ALL

                    SELECT d.icd10, 0 AS opd_cases, COUNT(DISTINCT d.an) AS ipd_cases
                    FROM iptdiag d
                    JOIN ipt ip ON ip.an = d.an
                    $ipdJoin
                    WHERE TRIM(d.diagtype)='1'
                      AND $ipdDateCol BETWEEN ? AND ?
                      AND $injuryFilter
                    GROUP BY d.icd10
                ) x
                LEFT JOIN icd101 i ON i.code = x.icd10
                GROUP BY x.icd10, icd10_name_th
                ORDER BY total_cases DESC
                LIMIT ?
            ";
            $bindings = array_merge($pcodes, [$start, $end], $pcodes, [$start, $end, $limit]);
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
