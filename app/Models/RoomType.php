<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'capacity_adults',
        'capacity_children',
        'size_m2',
        'bed_type',
        'base_price',
        'total_units',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'size_m2' => 'decimal:2',
            'base_price' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'room_type_services')
            ->using(RoomTypeService::class)
            ->withTimestamps();
    }

    public function availability(): HasMany
    {
        return $this->hasMany(RoomTypeAvailability::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
