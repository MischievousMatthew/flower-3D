<?php

namespace App\Services;

use App\Models\EmployeeInfo;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeQRCodeService
{
    /**
     * Generate QR code for an employee
     *
     * @param EmployeeInfo $employee
     * @return array
     */
    public function generateQRCode(EmployeeInfo $employee): array
    {
        // Create QR data payload
        $qrData = json_encode([
            'owner_id' => $employee->owner_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_id,
            'type' => 'employee_attendance'
        ]);

        // Generate QR code
        $qrCode = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->generate($qrData);

        // Save QR code to storage
        $filename = "qr-codes/employee-{$employee->id}-{$employee->employee_id}.png";
        Storage::disk('public')->put($filename, $qrCode);

        return [
            'qr_data' => $qrData,
            'qr_code_url' => Storage::disk('public')->url($filename),
            'qr_code_path' => $filename,
        ];
    }

    /**
     * Generate QR code as SVG
     *
     * @param EmployeeInfo $employee
     * @return string
     */
    public function generateQRCodeSVG(EmployeeInfo $employee): string
    {
        $qrData = json_encode([
            'owner_id' => $employee->owner_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_id,
            'type' => 'employee_attendance'
        ]);

        return QrCode::format('svg')
            ->size(300)
            ->errorCorrection('H')
            ->generate($qrData);
    }

    /**
     * Generate QR code as base64 PNG
     *
     * @param EmployeeInfo $employee
     * @return string
     */
    public function generateQRCodeBase64(EmployeeInfo $employee): string
    {
        $qrData = json_encode([
            'owner_id' => $employee->owner_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_id,
            'type' => 'employee_attendance'
        ]);

        $qrCode = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->generate($qrData);

        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    /**
     * Decode QR code data
     *
     * @param string $qrData
     * @return array|null
     */
    public function decodeQRData(string $qrData): ?array
    {
        try {
            $decoded = json_decode($qrData, true);
            
            if (!$decoded || !isset($decoded['owner_id']) || !isset($decoded['employee_id'])) {
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate QR code data
     *
     * @param array $qrData
     * @param int $currentOwnerId
     * @return bool
     */
    public function validateQRData(array $qrData, int $currentOwnerId): bool
    {
        // Check if QR data has required fields
        if (!isset($qrData['owner_id']) || !isset($qrData['employee_id']) || !isset($qrData['type'])) {
            return false;
        }

        // Check if type is correct
        if ($qrData['type'] !== 'employee_attendance') {
            return false;
        }

        // Check if owner_id matches current authenticated owner
        if ((int)$qrData['owner_id'] !== $currentOwnerId) {
            return false;
        }

        return true;
    }

    /**
     * Delete QR code file
     *
     * @param EmployeeInfo $employee
     * @return bool
     */
    public function deleteQRCode(EmployeeInfo $employee): bool
    {
        $filename = "qr-codes/employee-{$employee->id}-{$employee->employee_id}.png";
        
        if (Storage::disk('public')->exists($filename)) {
            return Storage::disk('public')->delete($filename);
        }

        return true;
    }
}