<?php

namespace App\Models;

use App\Support\ApiAreas;
use Illuminate\Database\Eloquent\Model;

/** An API request that ended in a server error (5xx), or a programmer error a person was shielded from */
class ApiErrorLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['method', 'path', 'action', 'status_code', 'exception', 'message', 'user_id', 'ip_address', 'duration_ms', 'reference', 'account_type', 'account_name', 'employee_id', 'location', 'shown_message', 'signature', 'resolved_at'];

    protected $casts = ['created_at' => 'datetime', 'resolved_at' => 'datetime'];

    /**
     * Which problem an error belongs to: the same kind of error at the same
     * place in the code (or the same feature, for older rows), whoever hit it.
     * IDs and numbers are left out, so each occurrence lands in one problem.
     */
    public static function signatureFor(array $row): string
    {
        $place = $row['location'] ?? null ?: (ApiAreas::fromAction($row['action'] ?? null) ?? (($row['method'] ?? '') . ' ' . preg_replace('/\d+/', '#', (string) ($row['path'] ?? ''))));

        return md5(implode('|', [
            class_basename((string) ($row['exception'] ?? '')),
            $place,
            (string) ApiAreas::cleanMessage($row['message'] ?? null),
        ]));
    }
}
