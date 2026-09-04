<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keep Add product photos on disk when validation fails.
 *
 * Browser file inputs cannot be restored from flashed old input, so validated
 * uploads are stored under a store-scoped draft folder and reattached on retry.
 */
final class ProductCreateImageDraft
{
    public const SESSION_KEY_PREFIX = 'product_create_image_draft.';

    /**
     * @return array{
     *     paths: list<string>,
     *     retained_paths: list<string>,
     *     new_index_to_path: array<int, string>,
     *     token: string
     * }
     */
    public static function capture(Request $request, Store $store, bool $stashNewUploads = true): array
    {
        $token = self::token($request, $store);
        $directory = self::directory($store, $token);
        $disk = Storage::disk('public');

        $retained = [];
        foreach (self::submittedExistingPaths($request) as $path) {
            if (self::isOwnedPath($store, $token, $path) && $disk->exists($path)) {
                $retained[] = $path;
            }
        }
        $retained = array_values(array_unique($retained));

        $newIndexToPath = [];
        if ($stashNewUploads) {
            foreach (self::uploadedFiles($request) as $uploadIndex => $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $stored = $file->store($directory, 'public');
                if (is_string($stored) && $stored !== '') {
                    $newIndexToPath[(int) $uploadIndex] = str_replace('\\', '/', $stored);
                }
            }
        }

        $ordered = [];
        $used = [];
        foreach ((array) $request->input('image_order', []) as $orderToken) {
            if (! is_string($orderToken) || $orderToken === '') {
                continue;
            }
            if (str_starts_with($orderToken, 'new:')) {
                $index = (int) substr($orderToken, 4);
                $path = $newIndexToPath[$index] ?? null;
                if (is_string($path) && $path !== '' && ! isset($used[$path])) {
                    $ordered[] = $path;
                    $used[$path] = true;
                }
            } elseif (str_starts_with($orderToken, 'existing:')) {
                $path = str_replace('\\', '/', substr($orderToken, strlen('existing:')));
                if (in_array($path, $retained, true) && ! isset($used[$path])) {
                    $ordered[] = $path;
                    $used[$path] = true;
                }
            }
        }

        foreach ($retained as $path) {
            if (! isset($used[$path])) {
                $ordered[] = $path;
                $used[$path] = true;
            }
        }

        ksort($newIndexToPath);
        foreach ($newIndexToPath as $path) {
            if (! isset($used[$path])) {
                $ordered[] = $path;
                $used[$path] = true;
            }
        }

        $ordered = array_slice($ordered, 0, 8);

        if ($disk->exists($directory)) {
            foreach ($disk->files($directory) as $file) {
                $normalized = str_replace('\\', '/', (string) $file);
                if (! in_array($normalized, $ordered, true)) {
                    $disk->delete($normalized);
                }
            }
        }

        return [
            'paths' => $ordered,
            'retained_paths' => array_values(array_filter(
                $retained,
                static fn (string $path): bool => in_array($path, $ordered, true)
            )),
            'new_index_to_path' => $newIndexToPath,
            'token' => $token,
        ];
    }

    /**
     * @param  array{paths?: list<string>, new_index_to_path?: array<int, string>}  $capture
     * @return array<string, mixed>
     */
    public static function mergeIntoOldInput(Request $request, array $capture): array
    {
        $input = $request->except(['product_images']);
        $paths = array_values(array_filter(
            $capture['paths'] ?? [],
            static fn ($path): bool => is_string($path) && $path !== ''
        ));
        $input['existing_image_paths'] = $paths;
        $input['image_order'] = array_map(
            static fn (string $path): string => 'existing:'.$path,
            $paths
        );

        $newMap = is_array($capture['new_index_to_path'] ?? null) ? $capture['new_index_to_path'] : [];
        if (isset($input['variants']) && is_array($input['variants'])) {
            foreach ($input['variants'] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $reference = trim((string) ($row['product_image_id'] ?? ''));
                if (! str_starts_with($reference, 'new:')) {
                    continue;
                }
                $uploadIndex = (int) substr($reference, 4);
                $path = $newMap[$uploadIndex] ?? null;
                $input['variants'][$index]['product_image_id'] = is_string($path) && $path !== ''
                    ? 'existing:'.$path
                    : '';
            }
        }

        return $input;
    }

    /**
     * @return list<string>
     */
    public static function retainedPaths(Request $request, Store $store): array
    {
        $token = (string) $request->session()->get(self::sessionKey($store->id), '');
        if ($token === '' || ! self::isSafeToken($token)) {
            return [];
        }

        $disk = Storage::disk('public');
        $kept = [];
        foreach (self::submittedExistingPaths($request) as $path) {
            if (self::isOwnedPath($store, $token, $path) && $disk->exists($path)) {
                $kept[] = $path;
            }
        }

        return array_values(array_unique($kept));
    }

    public static function adoptIntoOfficialPath(string $draftPath, Store $store): string
    {
        $disk = Storage::disk('public');
        $normalized = str_replace('\\', '/', $draftPath);
        if (! $disk->exists($normalized)) {
            throw new \RuntimeException('A saved product photo could not be found. Please add it again.');
        }

        $extension = strtolower((string) pathinfo($normalized, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $directory = ProductImageStorage::directoryForStore($store);
        do {
            $destination = $directory.'/'.Str::random(40).'.'.$extension;
        } while ($disk->exists($destination));

        $disk->put($destination, $disk->get($normalized));

        return $destination;
    }

    public static function forget(Request $request, Store $store): void
    {
        $key = self::sessionKey($store->id);
        $token = $request->session()->get($key);
        $request->session()->forget($key);
        if (! is_string($token) || ! self::isSafeToken($token)) {
            return;
        }

        Storage::disk('public')->deleteDirectory(self::directory($store, $token));
    }

    public static function isOwnedPath(Store $store, string $token, string $path): bool
    {
        if (! self::isSafeToken($token)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        if ($normalized === '' || str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
            return false;
        }

        $prefix = self::directory($store, $token).'/';

        return str_starts_with($normalized, $prefix);
    }

    public static function directory(Store $store, string $token): string
    {
        return 'products/'.$store->id.'/create-drafts/'.$token;
    }

    public static function sessionKey(int $storeId): string
    {
        return self::SESSION_KEY_PREFIX.$storeId;
    }

    public static function token(Request $request, Store $store): string
    {
        $key = self::sessionKey($store->id);
        $token = $request->session()->get($key);
        if (! is_string($token) || ! self::isSafeToken($token)) {
            $token = Str::lower(Str::random(40));
            $request->session()->put($key, $token);
        }

        return $token;
    }

    /**
     * @return list<string>
     */
    private static function submittedExistingPaths(Request $request): array
    {
        $paths = [];
        foreach ((array) $request->input('existing_image_paths', []) as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            $paths[] = str_replace('\\', '/', $path);
        }

        return $paths;
    }

    /**
     * @return array<int, UploadedFile>
     */
    private static function uploadedFiles(Request $request): array
    {
        $files = $request->file('product_images', []);
        if ($files instanceof UploadedFile) {
            return [0 => $files];
        }
        if (! is_array($files)) {
            return [];
        }

        $out = [];
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $out[(int) $index] = $file;
            }
        }

        return $out;
    }

    private static function isSafeToken(string $token): bool
    {
        return preg_match('/^[a-z0-9]{32,64}$/', $token) === 1;
    }
}
