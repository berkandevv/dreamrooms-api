<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'category',
        'scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class, 'hotel_services')
            ->using(HotelService::class)
            ->withTimestamps();
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'room_type_services')
            ->using(RoomTypeService::class)
            ->withTimestamps();
    }
}
