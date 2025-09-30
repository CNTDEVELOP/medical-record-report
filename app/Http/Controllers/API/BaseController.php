<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccessControl\Modules;
use Illuminate\Support\Facades\Cache;

class BaseController extends Controller
{
    protected $module_name;
    protected $message_ok;
    protected $module_id;
    protected $LINE_API = 'https://notify-api.line.me/api/notify';

    protected $envset = [
        'equipments' => [
            'name_display' => 'ครุภัณฑ์',
            'color' => '#0ea5e9',
            'color_icons' => '#2563eb',
            'icons' => 'mdi:human-trolley',
        ], 'materials' => [
            'name_display' => 'วัสดุ',
            'color' => '#d9f99d',
            'color_icons' => '#15803d',
            'icons' => 'academicons:open-materials',
        ], 'contacts' => [
            'name_display' => 'จ้างเหมาบริการ',
            'color' => '#fcd34d',
            'color_icons' => '#d97706',
            'icons' => 'openmoji:contacts',
        ], 'other' => [
            'name_display' => 'อื่นๆ',
            'color' => '#fda4af',
            'color_icons' => '#e11d48',
            'icons' => 'rivet-icons:question-mark',
        ],
    ];

    public function __construct()
    {
        // date_default_timezone_set('Asia/Bangkok');
        // define('CACHE_DURATION', 30 * 60);
        // $this->module_name = $this->getModuleName();
        // if ($this->module_name != 'http') {
        //     $cacheKey = 'module_name_' . $this->module_name;
        //     $this->module_id = Cache::rememberForever($cacheKey, function () {
        //         return Modules::where('name', $this->module_name)->first()->id;
        //     });
        // }
        // $this->message_ok = __('massage.ดึงข้อมูลสำเร็จ');
    }

    public function getModuleName($lower = true)
    {
        $class = get_called_class();
        $arr_class = explode('\\', $class);

        return $lower ? strtolower($arr_class[1]) : $arr_class[0];
    }

    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message = 'Data successful')
    {
        $response = [
            'status' => true,
            'data' => $result,
            'message' => $message,
        ];

        return response()->json($response, 200);
    }

    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 500)
    {
        $response = [
            'status' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
