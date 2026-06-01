<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'capacity_adults',
        'capacity_children',
        'size_m2',
        'bed_type',
        'base_price',
        'currency',
        'total_units',
        'free_cancellation_hours',
        'status',
    ];

    // Define la conversión de tipos de los atributos
    protected function casts(): array
    {
        return [
            'size_m2' => 'decimal:2',
            'base_price' => 'decimal:2',
        ];
    }

    // Filtra los tipos de habitación reservables
    public function scopeBookable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('hotel', fn ($hotelQuery) => $hotelQuery->published());
    }

    // Filtra los tipos de habitación de los hoteles de un propietario concreto
    public function scopeOwnedBy(Builder $query, int $ownerUserId): Builder
    {
        return $query->whereHas('hotel', fn ($hotelQuery) => $hotelQuery->ownedBy($ownerUserId));
    }

    // Devuelve el hotel del tipo de habitación
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    // Devuelve las imágenes del tipo de habitación
    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class);
    }

    // Devuelve la imagen de portada del tipo de habitación
    public function coverImage(): HasOne
    {
        return $this->hasOne(RoomTypeImage::class)
            ->where('is_cover', true)
            ->orderBy('sort_order');
    }

    // Devuelve los servicios del tipo de habitación
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'room_type_services')
            ->using(RoomTypeService::class)
            ->withTimestamps();
    }

    // Devuelve la disponibilidad del tipo de habitación
    public function availability(): HasMany
    {
        return $this->hasMany(RoomTypeAvailability::class);
    }

    // Devuelve las reservas del tipo de habitación
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
