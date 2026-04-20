<?php

namespace App\Support;

use App\Models\Store;
use Illuminate\Http\UploadedFile;

final class ProductImageStorage
{
    public static function directoryForStore(Store $store): string
    {
        return 'products/'.$store->id;
    }

    /**
     * Store a single uploaded image on the public disk; returns the relative path string.
     */
    public static function store(UploadedFile $file, Store $store): string
    {
        return $file->store(self::directoryForStore($store), 'public');
    }
}
