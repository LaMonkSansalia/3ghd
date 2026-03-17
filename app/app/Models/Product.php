<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'supplier_id', 'category_id', 'name', 'sku', 'brand', 'collection',
        'description', 'materials', 'colors', 'finishes', 'dimensions',
        'price_list', 'cost', 'markup_override', 'tags', 'notes',
        'source_url', 'source_file', 'is_active', 'is_featured', 'is_available',
    ];

    protected $casts = [
        'materials'       => 'array',
        'colors'          => 'array',
        'finishes'        => 'array',
        'dimensions'      => 'array',
        'tags'            => 'array',
        'price_list'      => 'decimal:2',
        'cost'            => 'decimal:2',
        'markup_override' => 'decimal:4',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'is_available'    => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Markup effettivo: override prodotto ?? default fornitore */
    public function effectiveMarkup(): float
    {
        return $this->markup_override ?? $this->supplier->markup_default ?? 1.35;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(300)
            ->nonQueued();

        $this->addMediaConversion('card')
            ->width(800)
            ->height(600)
            ->nonQueued();
    }
}
