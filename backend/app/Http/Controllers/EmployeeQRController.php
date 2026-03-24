<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeInfo;
use App\Services\EmployeeQRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeQRController extends Controller
{
    protected $qrCodeService;

    public function __construct(EmployeeQRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Generate QR code for an employee
     *
     * @param int $employeeId
     * @return JsonResponse
     */
    public function generate(int $employeeId): JsonResponse
    {
        $ownerId = auth()->user()->id;
        
        $employee = EmployeeInfo::where('id', $employeeId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $qrCode = $this->qrCodeService->generateQRCode($employee);

        return response()->json([
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => $qrCode
        ]);
    }

    /**
     * Get QR code as SVG
     *
     * @param int $employeeId
     * @return \Illuminate\Http\Response
     */
    public function getSVG(int $employeeId)
    {
        $ownerId = auth()->user()->id;
        
        $employee = EmployeeInfo::where('id', $employeeId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $svg = $this->qrCodeService->generateQRCodeSVG($employee);

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml');
    }

    /**
     * Get QR code as base64 PNG
     *
     * @param int $employeeId
     * @return JsonResponse
     */
    public function getBase64(int $employeeId): JsonResponse
    {
        $ownerId = auth()->user()->id;
        
        $employee = EmployeeInfo::where('id', $employeeId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $base64 = $this->qrCodeService->generateQRCodeBase64($employee);

        return response()->json([
            'success' => true,
            'data' => [
                'qr_code_base64' => $base64
            ]
        ]);
    }

    /**
     * Download QR code as PNG
     *
     * @param int $employeeId
     * @return \Illuminate\Http\Response
     */
    public function download(int $employeeId)
    {
        $ownerId = auth()->user()->id;
        
        $employee = EmployeeInfo::where('id', $employeeId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        $qrData = json_encode([
            'owner_id' => $employee->owner_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_id,
            'type' => 'employee_attendance'
        ]);

        $qrCode = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->generate($qrData);

        $filename = "QR-{$employee->employee_id}-{$employee->full_name}.png";

        return response($qrCode, 200)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}