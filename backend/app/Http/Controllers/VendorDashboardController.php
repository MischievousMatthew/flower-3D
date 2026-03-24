<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Vendor dashboard',
            'data' => [
                'stats' => [
                    'total_products' => 0,
                    'total_orders' => 0,
                    'revenue' => 0
                ]
            ]
        ]);
    }
}