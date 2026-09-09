<?php

namespace App\Support\Catalog;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared success/error responses for category, brand, and tag mutations.
 * HTML posts return to the products list unless the form asked to stay on Add product or Edit product.
 */
final class CatalogToolsResponse
{
    public static function json(string $kind, string $action, array $item, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'kind' => $kind,
            'action' => $action,
            'item' => $item,
            'message' => $message,
        ], $status);
    }

    public static function jsonError(string $message, int $status = 422): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
        ], $status);
    }

    public static function redirect(Request $request, string $message, string $title, string $meta): RedirectResponse
    {
        return redirect()
            ->route(self::routeName($request), self::routeParameters($request))
            ->with('success', $message)
            ->with('success_title', $title)
            ->with('success_meta', $meta);
    }

    public static function redirectWithErrors(Request $request, array $errors): RedirectResponse
    {
        return redirect()
            ->route(self::routeName($request), self::routeParameters($request))
            ->withErrors($errors);
    }

    public static function routeName(Request $request): string
    {
        $return = (string) $request->input('_catalog_return', '');
        if ($return === 'products.create') {
            return 'products.create';
        }

        if ($return === 'products.edit' && self::editReturnProductId($request) !== null) {
            return 'products.edit';
        }

        return 'products';
    }

    /**
     * @return array<string, mixed>
     */
    public static function routeParameters(Request $request): array
    {
        $route = self::routeName($request);
        if ($route === 'products.create') {
            return [
                'step' => 'organization',
            ];
        }

        if ($route === 'products.edit') {
            return [
                'product' => self::editReturnProductId($request),
                'step' => 'organization',
            ];
        }

        return [];
    }

    private static function editReturnProductId(Request $request): ?int
    {
        $raw = $request->input('_catalog_return_product_id');
        if ($raw === null || $raw === '' || ! ctype_digit((string) $raw)) {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
