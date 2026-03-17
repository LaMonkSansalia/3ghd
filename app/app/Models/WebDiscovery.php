<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebDiscovery extends Model
{
    protected $fillable = [
        'supplier_id', 'entry_url', 'status', 'pages_crawled',
        'items_found', 'items_imported', 'items', 'notes',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'items'        => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
