<?php

use App\Helpers\CloudinaryHelper;


if (!function_exists('storage_url')) {
    function storage_url($path)
    {
        return CloudinaryHelper::getUrl($path);
    }
}

if (!function_exists('is_encrypted')) {
    function is_encrypted($value): bool
    {
        try {
            decrypt($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('format_date')) {
    function format_date($date, $format = 'm/d/y \a\t H:i')
    {
        if (!$date) {
            return null;
        }
        
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return \Carbon\Carbon::parse($date)->format($format);
    }
}

if (!function_exists('format_business_type')) {
    function format_business_type($type)
    {
        $types = [
            'individual' => 'Sole Proprietor',
            'partnership' => 'Partnership',
            'corporation' => 'Corporation'
        ];
        
        return $types[$type] ?? $type;
    }
}

if (!function_exists('format_status')) {
    function format_status($status)
    {
        $statuses = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'under_review' => 'Under Review'
        ];
        
        return $statuses[$status] ?? $status;
    }
}