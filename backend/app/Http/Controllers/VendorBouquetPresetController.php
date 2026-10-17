<?php

namespace App\Http\Controllers;

use App\Support\BouquetPresets;
use Illuminate\Http\Request;

/**
 * Shop → 3D Bouquets → Presets: which occasions customers can pick in the
 * 3D customizer, and how many flowers each size has.
 */
class VendorBouquetPresetController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => BouquetPresets::forOwner($request->user()->id),
            'limits' => ['max_size' => BouquetPresets::MAX_SIZE, 'max_sizes' => BouquetPresets::MAX_SIZES_PER_OCCASION],
        ]);
    }

    public function update(Request $request)
    {
        $request->validate(['presets' => 'required|array']);
        $application = $request->user()->vendor;
        if (! $application) {
            return response()->json(['success' => false, 'message' => 'Shop profile not found.'], 404);
        }

        // Throws a 422 with per-occasion messages when something is off
        $application->bouquet_presets = BouquetPresets::normalize($request->input('presets'));
        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Occasion presets saved.',
            'data' => BouquetPresets::merge($application->bouquet_presets),
        ]);
    }
}
