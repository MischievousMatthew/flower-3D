<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\BouquetCustomizationOption as Option;
use App\Models\Product;
use App\Traits\ResolvesOwner;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Vendor management of 3D customizer wrappers, fillers and ribbons.
 */
class VendorCustomizationOptionController extends Controller
{
    use ResolvesOwner;

    private const MODEL_EXTENSIONS = ['glb', 'gltf'];

    /**
     * GET /api/vendor/customization-options
     */
    public function index(Request $request)
    {
        $ownerId = $this->resolveOwnerId($request);
        $options = Option::forOwner($ownerId)
            ->with('stockItem')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Option $o) => $this->withStock($o));

        return response()->json([
            'success' => true,
            'data' => $options,
            // Wrapping stock items a wrapper can use (the same ones ERP Stock and Inventory show)
            'wrapping_items' => $this->wrappingItems($ownerId),
        ]);
    }

    /** The option with its stock item (a wrapper's sheets, a filler's portions), as the page shows it */
    private function withStock(Option $option): array
    {
        $item = isset(Option::STOCK[$option->type]) ? $option->stockItem : null;

        // The relation would serialize as the whole product under the same key: send the summary instead
        return array_merge($option->withoutRelations()->toArray(), ['stock_item' => $item ? $this->stockItemData($item) : null]);
    }

    private function stockItemData(Product $p): array
    {
        return [
            'id'        => $p->id,
            'name'      => $p->product_name,
            'on_hand'   => (int) $p->quantity_in_stock,
            'unit'      => $p->stock_unit ?: 'sheet',
            'pack_unit' => $p->packSize() > 1 ? $p->pack_unit : null,
            'pack_size' => $p->packSize() > 1 ? $p->packSize() : null,
            // What the shop pays for one roll / sack (one sheet with no roll size); null when not set
            'pack_cost' => (float) $p->purchase_price > 0 ? round((float) $p->purchase_price * $p->packSize(), 2) : null,
        ];
    }

    /**
     * The cost per unit procurement keeps (purchase_price), from the form's
     * "Your cost" per roll or sack: ₱250 a sack of 20 = ₱12.50 each. Null when
     * the form didn't send it (leave the cost as it is); blank clears it.
     */
    private static function unitCostFrom(Request $request, ?int $packSize): ?float
    {
        if (! $request->has('pack_cost')) {
            return null;
        }
        $cost = $request->filled('pack_cost') ? max(0, (float) $request->input('pack_cost')) : 0.0;

        return round($cost / max(1, $packSize ?? 1), 2);
    }

    /** The shop's wrapping materials */
    private function wrappingItems(int $ownerId): array
    {
        return Product::query()
            ->where('owner_id', $ownerId)
            ->where('moderation_status', Product::MODERATION_INTERNAL)
            ->where('stock_kind', 'wrapping')
            ->orderBy('product_name')
            ->get()
            ->map(fn (Product $p) => $this->stockItemData($p))
            ->all();
    }

    /**
     * The wrapper or filler form's Stock section: stock_item is "none" (not
     * counted), "new" (a stock item named like the option) or the id of one of
     * the shop's items of that kind; stock_on_hand (stock_sheets for wrappers)
     * is the count on hand, in sheets or portions, and pack_size how many of
     * them a roll or sack gives. A changed count is saved as a stock count in
     * the item's history.
     */
    private function saveStock(Request $request, Option $option, int $ownerId): void
    {
        $how = Option::STOCK[$option->type] ?? null;
        if (! $how || ! $request->has('stock_item')) {
            return;
        }
        $choice = (string) $request->input('stock_item');
        if ($choice === '' || $choice === 'none') {
            $option->update(['stock_product_id' => null]);
            return;
        }

        $field = $request->filled('stock_on_hand') ? 'stock_on_hand' : 'stock_sheets';
        $onHand = $request->filled($field) ? max(0, (int) $request->input($field)) : null;
        $packSize = (int) $request->input('pack_size') >= 2 ? (int) $request->input('pack_size') : null;
        $unitCost = self::unitCostFrom($request, $packSize);
        $stock = app(\App\Services\InventoryStockService::class);

        if ($choice === 'new') {
            $item = $stock->createMaterial($ownerId, [
                'name'       => $option->name,
                'stock_kind' => $how['kind'],
                'stock_unit' => $how['unit'],
                'pack_unit'  => $packSize ? $how['pack'] : null,
                'pack_size'  => $packSize,
                'on_hand'    => $onHand ?? 0,
                'unit_cost'  => $unitCost ?? 0,
            ], $request->user());
        } else {
            $item = Product::query()
                ->whereKey((int) $choice)
                ->where('owner_id', $ownerId)
                ->where('moderation_status', Product::MODERATION_INTERNAL)
                ->where('stock_kind', $how['kind'])
                ->first();
            if (! $item) {
                throw new \InvalidArgumentException($option->type === Option::TYPE_WRAPPER ? 'Pick one of your wrapping items.' : "Pick one of your {$option->type} stock items.");
            }
            $item->update(array_merge(
                ['pack_unit' => $packSize ? ($item->pack_unit ?: $how['pack']) : null, 'pack_size' => $packSize],
                $unitCost !== null ? ['purchase_price' => $unitCost] : [],
            ));
            if ($onHand !== null && $onHand !== (int) $item->quantity_in_stock) {
                $stock->adjust($item, 'set', $onHand, 'count', "Counted in 3D Bouquet Management ({$option->name})", $request->user());
            }
        }

        $option->update(['stock_product_id' => $item->id]);
    }

    /**
     * POST /api/vendor/customization-options
     */
    public function store(Request $request)
    {
        $type = (string) $request->input('type');
        $validator = Validator::make($request->all(), array_merge(
            ['type' => ['required', Rule::in(Option::TYPES)]],
            $this->rules($type, true)
        ));
        $this->validateModelExtension($validator, $request);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ownerId = $this->resolveOwnerId($request);

        try {
            $option = new Option($this->payload($request, $type));
            $option->owner_id = $ownerId;
            $option->type = $type;
            $option->sort_order = (int) Option::forOwner($ownerId)->where('type', $type)->max('sort_order') + 1;

            if (in_array($type, Option::MODEL_TYPES, true)) {
                $this->storeModel($option, $request->file('model_file'));
            }
            // A card always has a photo; a wrapper, filler or ribbon may have one
            if ($type === Option::TYPE_CARD || (isset(Option::STOCK[$type]) && $request->hasFile('image_file'))) {
                $this->storeImage($option, $request->file('image_file'));
            }

            $option->save();
            $this->saveStock($request, $option, $ownerId);

            return response()->json(['success' => true, 'data' => $this->withStock($option->refresh()->load('stockItem')), 'wrapping_items' => $this->wrappingItems($ownerId), 'message' => ($type === Option::TYPE_WRAP_MODEL ? '3D wrapper' : ucfirst($type)) . ' added.'], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'errors' => ['stock_item' => [$e->getMessage()]]], 422);
        } catch (\Throwable $e) {
            Log::error('VendorCustomizationOptionController@store: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => "The {$type} could not be saved."], 500);
        }
    }

    /**
     * POST /api/vendor/customization-options/{id}
     * (POST rather than PUT so a replacement filler model can be uploaded.)
     */
    public function update(Request $request, $id)
    {
        $option = Option::forOwner($this->resolveOwnerId($request))->find($id);
        if (!$option) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $validator = Validator::make($request->all(), $this->rules($option->type, false));
        $this->validateModelExtension($validator, $request);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $previousModelPath = $option->model_path;
            $previousImagePath = $option->image_path;
            $option->fill($this->payload($request, $option->type));

            if (in_array($option->type, Option::MODEL_TYPES, true) && $request->hasFile('model_file')) {
                $this->storeModel($option, $request->file('model_file'));
            }
            $hasPhoto = $option->type === Option::TYPE_CARD || isset(Option::STOCK[$option->type]);
            if ($hasPhoto && $request->hasFile('image_file')) {
                $this->storeImage($option, $request->file('image_file'));
            } elseif (isset(Option::STOCK[$option->type]) && $request->boolean('remove_image')) {
                // A wrapper's, filler's or ribbon's photo taken off (the file is deleted below)
                $option->image_url = null;
                $option->image_path = null;
            }

            $option->save();
            $ownerId = $this->resolveOwnerId($request);
            $this->saveStock($request, $option, $ownerId);

            if ($previousModelPath && $previousModelPath !== $option->model_path) {
                $this->destroyModel($previousModelPath);
            }
            if ($previousImagePath && $previousImagePath !== $option->image_path) {
                $this->destroyImage($previousImagePath);
            }

            return response()->json(['success' => true, 'data' => $this->withStock($option->refresh()->load('stockItem')), 'wrapping_items' => $this->wrappingItems($ownerId), 'message' => 'Changes saved.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'errors' => ['stock_item' => [$e->getMessage()]]], 422);
        } catch (\Throwable $e) {
            Log::error('VendorCustomizationOptionController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'The changes could not be saved.'], 500);
        }
    }

    /**
     * PATCH /api/vendor/customization-options/{id}/active
     * Wrappers and fillers: switched off, they're hidden from the customizer (a filler whose stock isn't counted is marked sold out this way).
     */
    public function setActive(Request $request, $id)
    {
        $option = Option::forOwner($this->resolveOwnerId($request))->find($id);
        if (!$option || ! isset(Option::STOCK[$option->type])) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $validator = Validator::make($request->all(), ['is_active' => 'required|boolean']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $option->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['success' => true, 'data' => $option]);
    }

    /**
     * DELETE /api/vendor/customization-options/{id}
     */
    public function destroy(Request $request, $id)
    {
        $option = Option::forOwner($this->resolveOwnerId($request))->find($id);
        if (!$option) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $modelPath = $option->model_path;
        $imagePath = $option->image_path;
        $option->delete();
        if ($modelPath) {
            $this->destroyModel($modelPath);
        }
        if ($imagePath) {
            $this->destroyImage($imagePath);
        }

        return response()->json(['success' => true, 'message' => 'Item removed.']);
    }

    private function rules(string $type, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';
        $rules = [
            'name' => "{$required}|string|max:120",
            'description' => 'nullable|string|max:500',
            'price' => 'nullable|numeric|min:0|max:100000',
        ];

        if (in_array($type, [Option::TYPE_WRAPPER, Option::TYPE_RIBBON], true)) {
            $rules['color_hex'] = [$creating ? 'required' : 'sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        if ($type === Option::TYPE_WRAPPER) {
            $rules['is_active'] = 'nullable|boolean';
            // A print on the paper: none, newspaper, or a word written all over it
            $rules['pattern'] = 'nullable|in:none,newspaper,words';
            $rules['pattern_text'] = 'nullable|required_if:pattern,words|string|max:' . \App\Support\BouquetPresets::MAX_PATTERN_WORD;
            // The word's size, as a percent of the normal size
            $rules['pattern_size'] = 'nullable|integer|min:' . \App\Support\BouquetPresets::MIN_PATTERN_SIZE . '|max:' . \App\Support\BouquetPresets::MAX_PATTERN_SIZE;
        }

        if ($type === Option::TYPE_FILLER) {
            // GLB is often sent as application/octet-stream, so check the
            // extension instead of the MIME type (as products do).
            $rules['model_file'] = ($creating ? 'required' : 'nullable') . '|file|max:51200';
            $rules['is_active'] = 'nullable|boolean';
        }

        if (isset(Option::STOCK[$type])) {
            // A photo customers see first (optional), or remove_image to take it off
            $rules['image_file'] = 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120';
            $rules['remove_image'] = 'nullable|boolean';
            // Switched off = hidden (how an uncounted filler or ribbon is marked sold out)
            $rules['is_active'] = 'nullable|boolean';
            // Sheets or portions on hand (blank = not counted) and how many a roll or sack gives
            $rules['stock_on_hand'] = 'nullable|integer|min:0|max:1000000';
            $rules['stock_sheets'] = 'nullable|integer|min:0|max:1000000';
            $rules['pack_size'] = 'nullable|integer|min:2|max:100000';
            // Your cost per roll / sack (per sheet for a wrapper with no roll size)
            $rules['pack_cost'] = 'nullable|numeric|min:0|max:1000000';
        }

        if ($type === Option::TYPE_WRAP_MODEL) {
            // Cloudinary's free plan takes files up to 10 MB
            $rules['model_file'] = ($creating ? 'required' : 'nullable') . '|file|max:10240';
        }

        if ($type === Option::TYPE_CARD) {
            // Each size the card comes in has its own price; the card's
            // `price` is kept as the lowest of them for listings.
            $rules['image_file'] = ($creating ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:5120';
            $rules['card_limit_type'] = "{$required}|in:" . Option::CARD_LIMIT_WORDS . ',' . Option::CARD_LIMIT_LETTERS;
            $rules['card_limit'] = "{$required}|integer|min:1|max:1000";
            $rules['card_sizes'] = "{$required}|array|min:1|max:" . count(Option::CARD_SIZES);
            $rules['card_sizes.*.label'] = ['required', 'distinct', Rule::in(Option::CARD_SIZES)];
            $rules['card_sizes.*.price'] = 'required|numeric|min:0|max:100000';
        }

        return $rules;
    }

    private function validateModelExtension($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $file = $request->file('model_file');
            if (!$file instanceof UploadedFile) {
                return;
            }
            if (!in_array(strtolower($file->getClientOriginalExtension()), self::MODEL_EXTENSIONS, true)) {
                $validator->errors()->add('model_file', 'The 3D model must be a GLB or GLTF file.');
            }
        });
    }

    private function payload(Request $request, string $type): array
    {
        $data = [];
        foreach (['name', 'description'] as $field) {
            if ($request->has($field)) {
                $data[$field] = trim((string) $request->input($field)) ?: null;
            }
        }
        // Wrappers, ribbons and fillers have no price of their own: the occasion's
        // base price covers them. Cards are priced per size below.
        if ($type !== Option::TYPE_CARD) {
            $data['price'] = 0;
        }
        if ($request->has('color_hex') && !in_array($type, Option::MODEL_TYPES, true)) {
            $data['color_hex'] = strtolower($request->input('color_hex'));
        }
        if ($request->has('is_active') && isset(Option::STOCK[$type])) {
            $data['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('pattern') && $type === Option::TYPE_WRAPPER) {
            $clean = \App\Support\BouquetPresets::cleanPattern([
                'type' => $request->input('pattern'),
                'text' => $request->input('pattern_text'),
                'size' => $request->input('pattern_size'),
            ]);
            $data['pattern'] = $clean['type'] ?? null;
            $data['pattern_text'] = $clean['text'] ?? null;
            $data['pattern_size'] = isset($clean['text']) ? ($clean['size'] ?? 100) : null;
        }

        if ($type === Option::TYPE_CARD) {
            if ($request->has('card_limit_type')) {
                $data['card_limit_type'] = $request->input('card_limit_type');
            }
            if ($request->has('card_limit')) {
                $data['card_limit'] = (int) $request->input('card_limit');
            }
            if ($request->has('card_sizes')) {
                // Stored smallest first, whatever order the vendor entered them in
                $sizes = collect($request->input('card_sizes'))
                    ->map(fn ($size) => ['label' => $size['label'], 'price' => round((float) $size['price'], 2)])
                    ->sortBy(fn ($size) => array_search($size['label'], Option::CARD_SIZES, true))
                    ->values()
                    ->all();
                $data['card_sizes'] = $sizes;
                $data['price'] = collect($sizes)->min('price') ?? 0;
            }
        }

        return $data;
    }

    private function storeImage(Option $option, UploadedFile $file): void
    {
        $result = CloudinaryHelper::upload($file, [
            'folder' => $option->type === Option::TYPE_CARD ? 'customization_cards' : 'customization_photos',
            'resource_type' => 'image',
        ]);

        $option->image_url = $result['secure_url'];
        $option->image_path = $result['public_id'];
    }

    private function destroyImage(string $path): void
    {
        try {
            CloudinaryHelper::destroy($path);
        } catch (\Throwable $e) {
            Log::warning('Could not delete card image ' . $path . ': ' . $e->getMessage());
        }
    }

    private function storeModel(Option $option, UploadedFile $file): void
    {
        $result = CloudinaryHelper::upload($file, [
            'folder' => 'customization_models',
            'resource_type' => 'raw',
        ]);

        $option->model_url = $result['secure_url'];
        $option->model_path = $result['public_id'];
    }

    private function destroyModel(string $path): void
    {
        try {
            CloudinaryHelper::destroy($path, ['resource_type' => 'raw']);
        } catch (\Throwable $e) {
            Log::warning('Could not delete customization model ' . $path . ': ' . $e->getMessage());
        }
    }
}
