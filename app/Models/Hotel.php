<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'description',
        'stars',
        'country',
        'region',
        'city',
        'address',
        'postal_code',
        'latitude',
        'longitude',
        'contact_email',
        'contact_phone',
        'check_in_time',
        'check_out_time',
        'cancellation_policy',
        'pets_allowed',
        'smoking_allowed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'pets_allowed' => 'boolean',
            'smoking_allowed' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'hotel_services')
            ->using(HotelService::class)
            ->withTimestamps();
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
