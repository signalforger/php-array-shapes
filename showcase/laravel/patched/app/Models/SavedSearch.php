<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SavedSearch extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'query',
        'filters',
        'webhook_url',
        'webhook_secret',
        'last_notified_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'last_notified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->webhook_secret)) {
                $model->webhook_secret = Str::random(32);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
