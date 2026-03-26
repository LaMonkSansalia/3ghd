<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Supplier extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name', 'website', 'catalog_format', 'contact_name', 'contact_email',
        'contact_phone', 'notes', 'markup_default', 'last_imported_at', 'is_active', 'color',
    ];

    /** Colore badge assegnato manualmente o auto-generato dall'ID. */
    public function badgeColor(): string
    {
        if ($this->color) {
            return $this->color;
        }

        $palette = ['blue', 'green', 'orange', 'purple', 'teal', 'pink', 'indigo', 'amber', 'rose', 'cyan'];

        return $palette[$this->id % count($palette)];
    }

    protected $casts = [
        'markup_default' => 'decimal:4',
        'last_imported_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }
}
