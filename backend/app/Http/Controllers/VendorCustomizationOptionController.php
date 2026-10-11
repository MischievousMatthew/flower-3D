<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\BouquetCustomizationOption as Option;
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
        $options = Option::forOwner($this->resolveOwnerId($request))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $options]);
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

            if ($type === Option::TYPE_FILLER) {
                $this->storeModel($option, $request->file('model_file'));
            }
            if ($type === Option::TYPE_CARD) {
                $this->storeImage($option, $request->file('image_file'));
            }

            $option->save();

            return response()->json(['success' => true, 'data' => $option->refresh(), 'message' => ucfirst($type) . ' added.'], 201);
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

            if ($option->type === Option::TYPE_FILLER && $request->hasFile('model_file')) {
                $this->storeModel($option, $request->file('model_file'));
            }
            if ($option->type === Option::TYPE_CARD && $request->hasFile('image_file')) {
                $this->storeImage($option, $request->file('image_file'));
            }

            $option->save();

            if ($previousModelPath && $previousModelPath !== $option->model_path) {
                $this->destroyModel($previousModelPath);
            }
            if ($previousImagePath && $previousImagePath !== $option->image_path) {
                $this->destroyImage($previousImagePath);
            }

            return response()->json(['success' => true, 'data' => $option, 'message' => 'Changes saved.']);
        } catch (\Throwable $e) {
            Log::error('VendorCustomizationOptionController@update: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'The changes could not be saved.'], 500);
        }
    }

    /**
     * PATCH /api/vendor/customization-options/{id}/active
     * Wrappers only: switched-off wrappers are hidden from the customizer.
     */
    public function setActive(Request $request, $id)
    {
        $option = Option::forOwner($this->resolveOwnerId($request))->find($id);
        if (!$option || $option->type !== Option::TYPE_WRAPPER) {
            return response()->json(['success' => false, 'message' => 'Wrapper not found.'], 404);
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
        }

        if ($type === Option::TYPE_FILLER) {
            // GLB is often sent as application/octet-stream, so check the
            // extension instead of the MIME type (as products do).
            $rules['model_file'] = ($creating ? 'required' : 'nullable') . '|file|max:51200';
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
        if ($request->has('price')) {
            $data['price'] = round((float) $request->input('price'), 2);
        }
        if ($request->has('color_hex') && $type !== Option::TYPE_FILLER) {
            $data['color_hex'] = strtolower($request->input('color_hex'));
        }
        if ($request->has('is_active') && $type === Option::TYPE_WRAPPER) {
            $data['is_active'] = $request->boolean('is_active');
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
            'folder' => 'customization_cards',
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
