<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Picqer\Barcode\BarcodeGeneratorSVG;

class DeliveryBarcodeController extends Controller
{
    public function show(int $orderId): JsonResponse
    {
        // Find delivery by order_id
        $delivery = Delivery::where('order_id', $orderId)->firstOrFail();

        // Generate barcode value if not yet set (idempotent)
        if (empty($delivery->barcode)) {
            $delivery->barcode = 'DLV-' . strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 10));
            $delivery->save();
        }

        // Generate correct Code128 SVG using picqer
        $generator = new BarcodeGeneratorSVG();
        $svg = $generator->getBarcode(
            $delivery->barcode,
            $generator::TYPE_CODE_128,
            2,    // bar width multiplier
            60,   // bar height in px
        );

        // Wrap SVG with human-readable label below
        $labeled = $this->addLabelToSvg($svg, $delivery->barcode);

        return response()->json([
            'barcode_value' => $delivery->barcode,
            'barcode_svg'   => $labeled,
        ]);
    }

    private function addLabelToSvg(string $svg, string $label): string
    {
        // Inject text element before closing </svg> tag
        $textEl = sprintf(
            '<text x="50%%" y="100%%" text-anchor="middle" '
            . 'font-family="monospace" font-size="11" fill="#1e293b" '
            . 'dy="-4">%s</text>',
            htmlspecialchars($label)
        );

        // Add 20px bottom padding for the label
        $svg = preg_replace('/height="(\d+)"/', 'height="$1"', $svg);
        $svg = str_replace('</svg>', $textEl . '</svg>', $svg);

        return $svg;
    }
}