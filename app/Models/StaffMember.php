<?php

namespace App\Models;

use App\Enums\StaffType;
use App\Models\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class StaffMember extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, LogsActivity, HasTranslations;

    protected $fillable = [
        'first_name',
        'last_name',
        'role',
        'type',
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
