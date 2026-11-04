<?php

namespace App\Http\Controllers;

use App\Models\BouquetCustomizationOption;
use App\Support\BouquetPresets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $presets = BouquetPresets::normalize($request->input('presets'));

        $ownerId = $request->user()->id;
        $wanted = array_values(array_filter(array_map(fn ($p) => BouquetPresets::modelId($p['design'] ?? null), $presets)));
        if ($wanted) {
            $owned = BouquetCustomizationOption::forOwner($ownerId)
                ->where('type', BouquetCustomizationOption::TYPE_WRAP_MODEL)
                ->whereNotNull('model_url')
                ->whereIn('id', $wanted)
                ->pluck('id')
                ->all();
            $errors = [];
            foreach ($presets as $key => $p) {
                $id = BouquetPresets::modelId($p['design'] ?? null);
                if ($id && ! in_array($id, $owned, true)) {
                    $errors["$key.design"][] = 'That 3D wrapper was removed. Choose another bouquet style.';
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        $application->bouquet_presets = $presets;
        $application->save();

        return response()->json([
            'success' => true,
            'message' => 'Occasion presets saved.',
            'data' => BouquetPresets::withModels(BouquetPresets::merge($application->bouquet_presets), $ownerId),
        ]);
    }
}
