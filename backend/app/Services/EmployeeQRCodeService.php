<?php

namespace App\Services;

use App\Models\EmployeeInfo;
use App\Helpers\CloudinaryHelper;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class EmployeeQRCodeService
{
    public const TYPE = 'employee_attendance';

    /**
     * The text inside an employee's QR code. It is signed with the app key, so a code
     * can't be made by hand, and carries the QR version, so reissuing one retires old copies.
     */
    public function payload(EmployeeInfo $employee): string
    {
        $version = (int) ($employee->qr_version ?? 1);

        return json_encode([
            'owner_id' => $employee->owner_id,
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_id,
            'type' => self::TYPE,
            'v' => $version,
            'sig' => $this->signature((int) $employee->owner_id, (int) $employee->id, $version),
        ]);
    }

    public function signature(int $ownerId, int $employeeId, int $version): string
    {
        return substr(hash_hmac('sha256', "{$ownerId}|{$employeeId}|{$version}", (string) config('app.key')), 0, 32);
    }

    /**
     * Check a scanned code for this shop.
     *
     * @return array{employee: EmployeeInfo, legacy: bool}|array{error: string}
     */
    public function verify(string $qrText, int $ownerId): array
    {
        $data = $this->decodeQRData($qrText);
        if (! $data || ($data['type'] ?? null) !== self::TYPE) {
            return ['error' => 'This isn\'t an attendance QR code.'];
        }
        if ((int) $data['owner_id'] !== $ownerId) {
            return ['error' => 'This QR code is for another shop.'];
        }

        $employee = EmployeeInfo::forOwner($ownerId)->find((int) $data['employee_id']);
        if (! $employee) {
            return ['error' => 'No one at this shop has this QR code.'];
        }

        // Codes printed before signing still work for now, but each scan goes to review
        if (! isset($data['sig'])) {
            return ['employee' => $employee, 'legacy' => true];
        }

        $version = (int) ($data['v'] ?? 0);
        $expected = $this->signature($ownerId, (int) $employee->id, $version);
        if (! hash_equals($expected, (string) $data['sig'])) {
            return ['error' => 'This QR code isn\'t genuine.'];
        }
        if ($version !== (int) ($employee->qr_version ?? 1)) {
            return ['error' => 'This QR code was replaced. Use the newest one.'];
        }

        return ['employee' => $employee, 'legacy' => false];
    }

    /**
     * Generate QR code for an employee
     *
     * @param EmployeeInfo $employee
     * @return array
     */
    public function generateQRCode(EmployeeInfo $employee): array
    {
        // Create QR data payload
        $qrData = $this->payload($employee);

        // Generate QR code
        $qrCode = QrCode::format('png')
            ->size(300)
            ->errorCorrection('H')
            ->generate($qrData);

        // Save QR code to Cloudinary using a temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'qr_');
        file_put_contents($tempPath, $qrCode);

        try {
            $result = CloudinaryHelper::upload($tempPath, [
                'folder'    => 'qr-codes',
                'public_id' => "employee-{$employee->id}-{$employee->employee_id}",
            ]);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }

        return [
            'qr_data'      => $qrData,
            'qr_code_url'  => $result['secure_url'],
            'qr_code_path' => $result['public_id'],
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
        $qrData = $this->payload($employee);

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
        $qrData = $this->payload($employee);

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
        $publicId = "qr-codes/employee-{$employee->id}-{$employee->employee_id}";
        CloudinaryHelper::destroy($publicId);
        return true;
    }
}