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

            // สิทธิประกันสังคมชุดนี้
            $pcodes = ['AL', 'A7', 'A8'];
            $inPcode = implode(',', array_fill(0, count($pcodes), '?'));

            // นิยามชื่อหัตถการ (เลือก operation_detail_name ถ้ามี ไม่งั้นใช้ operation_name)
            $opNameExpr = "COALESCE(NULLIF(TRIM(ol.operation_detail_name),''), NULLIF(TRIM(ol.operation_name),''), '(ไม่ระบุหัตถการ)')";

            // เงื่อนไขแผนกกระดูก: ใช้ send_depcode = '080' หรือ main_dep = '080'
            // - join ovst ด้วย vn (บางเคสอาจไม่มี vn)
            // - fallback: ovst ตัวล่าสุดของ hn ก่อนหรือในวันผ่าตัด เพื่อเอา pttype ได้กรณีไม่มี vn
            $sqlCommonJoins = "
                LEFT JOIN ovst o  ON o.vn = ol.vn
                LEFT JOIN ovst o2 ON o2.hn = ol.hn
                                   AND o2.vstdate = (
                                        SELECT MAX(vstdate)
                                        FROM ovst
                                        WHERE hn = ol.hn AND vstdate <= ol.operation_date
                                   )
                LEFT JOIN pttype p  ON p.pttype = o.pttype
                LEFT JOIN pttype p2 ON p2.pttype = o2.pttype
            ";

            // เงื่อนไขสิทธิ (จับจาก ovst เท่านั้น ตามที่กำหนด)
            $wherePcode = "(p.pcode IN ($inPcode) OR p2.pcode IN ($inPcode))";

            // เงื่อนไขแผนก
            $whereDep = "(ol.send_depcode = '080' OR o.main_dep = '080')";

            // เงื่อนไขช่วงวัน (อิงวันผ่าตัด)
            $whereDate = "ol.operation_date BETWEEN ? AND ?";

            // เงื่อนไขแยก OPD/IPD จาก operation_list
            $whereOPD = "(ol.an IS NULL OR ol.patient_type = 'OPD')";
            $whereIPD = "(ol.an IS NOT NULL OR ol.patient_type = 'IPD')";

            // ====== สร้าง SQL ตาม mode ======
            if ($mode === 'opd') {
                $sql = "
                    SELECT t.op_name, t.cases AS opd_cases
                    FROM (
                        SELECT
                            $opNameExpr AS op_name,
                            COUNT(DISTINCT ol.operation_id) AS cases
                        FROM operation_list ol
                        $sqlCommonJoins
                        WHERE $whereDate
                          AND $whereDep
                          AND $wherePcode
                          AND $whereOPD
                        GROUP BY op_name
                    ) t
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $bindings = array_merge($pcodes, $pcodes, [$start, $end, $limit]); // pcode x2 + dates + limit
                $rows = DB::connection('mysql_252')->select($sql, $bindings);
                return $this->sendResponse($rows);
            }

            if ($mode === 'ipd') {
                $sql = "
                    SELECT t.op_name, t.cases AS ipd_cases
                    FROM (
                        SELECT
                            $opNameExpr AS op_name,
                            COUNT(DISTINCT ol.operation_id) AS cases
                        FROM operation_list ol
                        $sqlCommonJoins
                        WHERE $whereDate
                          AND $whereDep
                          AND $wherePcode
                          AND $whereIPD
                        GROUP BY op_name
                    ) t
                    ORDER BY t.cases DESC
                    LIMIT ?
                ";
                $bindings = array_merge($pcodes, $pcodes, [$start, $end, $limit]);
                $rows = DB::connection('mysql_252')->select($sql, $bindings);
                return $this->sendResponse($rows);
            }

            // mode === 'all'
            $sql = "
                SELECT 
                    x.op_name,
                    SUM(x.opd_cases) AS opd_cases,
                    SUM(x.ipd_cases) AS ipd_cases,
                    (SUM(x.opd_cases) + SUM(x.ipd_cases)) AS total_cases
                FROM (
                    -- OPD
                    SELECT
                        $opNameExpr AS op_name,
                        COUNT(DISTINCT ol.operation_id) AS opd_cases,
                        0 AS ipd_cases
                    FROM operation_list ol
                    $sqlCommonJoins
                    WHERE $whereDate
                      AND $whereDep
                      AND $wherePcode
                      AND $whereOPD
                    GROUP BY op_name

                    UNION ALL

                    -- IPD
                    SELECT
                        $opNameExpr AS op_name,
                        0 AS opd_cases,
                        COUNT(DISTINCT ol.operation_id) AS ipd_cases
                    FROM operation_list ol
                    $sqlCommonJoins
                    WHERE $whereDate
                      AND $whereDep
                      AND $wherePcode
                      AND $whereIPD
                    GROUP BY op_name
                ) x
                GROUP BY x.op_name
                ORDER BY total_cases DESC
                LIMIT ?
            ";
            $bindings = array_merge(
                $pcodes, $pcodes, [$start, $end],  // OPD
                $pcodes, $pcodes, [$start, $end],  // IPD
                [$limit]
            );
            $rows = DB::connection('mysql_252')->select($sql, $bindings);
            return $this->sendResponse($rows);

        } catch (\Throwable $e) {
            return $this->sendError($e->getMessage());
        }
    }
}
