<?php

namespace App\Http\Middleware;

use App\Support\ProductCode;
use Closure;
use Illuminate\Http\Request;

/**
 * Customer-facing product routes take the product's code, never its number:
 * the code in {id} / {productId} is swapped for the id before the controller
 * runs, and a plain number (or a bad code) gets "not found".
 */
class DecodeProductCode
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        foreach (['id', 'productId'] as $name) {
            if (! $route->hasParameter($name)) {
                continue;
            }
            $id = ProductCode::decode((string) $route->parameter($name));
            if ($id === null) {
                return response()->json(['success' => false, 'message' => "We couldn't find that flower."], 404);
            }
            $route->setParameter($name, $id);
        }

        return $next($request);
    }
}
