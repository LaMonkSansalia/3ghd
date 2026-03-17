<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'supplier_id', 'format', 'file_name', 'file_path', 'status',
        'total_rows', 'imported_count', 'updated_count', 'skipped_count', 'errors_count',
        'error_details', 'column_mapping', 'extracted_items', 'ai_assisted', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'error_details'    => 'array',
        'column_mapping'   => 'array',
        'extracted_items'  => 'array',
        'ai_assisted' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
