<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'saved_search_id',
        'job_ids',
        'payload',
        'status',
        'attempts',
        'http_status',
        'response',
        'delivered_at',
    ];

    protected $casts = [
        'job_ids' => 'array',
        'payload' => 'array',
        'attempts' => 'integer',
        'http_status' => 'integer',
        'delivered_at' => 'datetime',
    ];

    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }
}
