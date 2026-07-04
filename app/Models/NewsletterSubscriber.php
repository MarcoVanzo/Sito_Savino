<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'ip_address',
        'source',
        'synced_to_ac',
        'ac_contact_id',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'synced_to_ac' => 'boolean',
            'subscribed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('synced_to_ac', true);
    }

    public function scopeUnsynced(Builder $query): Builder
    {
        return $query->where('synced_to_ac', false);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unsubscribed_at');
    }
}
