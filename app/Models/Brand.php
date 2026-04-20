<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Media: store files on the public disk; persist only relative paths in string columns (e.g. logo).
     *
     * @var list<string>
     */
    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'short_description',
        'description',
        'logo',
        'status',
        'sort_order',
        'featured',
        'seo_title',
        'seo_description',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
