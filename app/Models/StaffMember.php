<?php

namespace App\Models;

use App\Enums\StaffType;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class StaffMember extends Model implements HasMedia
{
    use HasFactory, HasTranslations, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'first_name',
        'last_name',
        'role',
        'type',
        'section',
        'sort_order',
    ];

    public $translatable = ['role', 'biography'];

    protected $casts = [
        'type' => StaffType::class,
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
