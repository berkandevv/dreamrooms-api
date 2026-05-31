<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hotel extends Model
{
    use HasFactory;

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
        'tax_rate_percent',
        'discount_rate_percent',
        'pets_allowed',
        'smoking_allowed',
        'status',
    ];

    // Define la conversión de tipos de los atributos
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'check_in_time' => 'datetime:H:i:s',
            'check_out_time' => 'datetime:H:i:s',
            'tax_rate_percent' => 'decimal:2',
            'discount_rate_percent' => 'decimal:2',
            'pets_allowed' => 'boolean',
            'smoking_allowed' => 'boolean',
        ];
    }

    // Filtra los hoteles publicados
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    // Añade las métricas del resumen público
    public function scopeWithPublicSummaryMetrics(Builder $query): Builder
    {
        return $query
            ->withMin([
                'roomTypes' => fn ($roomTypeQuery) => $roomTypeQuery->where('status', 'active'),
            ], 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
            ]);
    }

    // Devuelve el propietario del hotel
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    // Devuelve las imágenes del hotel
    public function images(): HasMany
    {
        return $this->hasMany(HotelImage::class);
    }

    // Devuelve la imagen de portada del hotel
    public function coverImage(): HasOne
    {
        return $this->hasOne(HotelImage::class)
            ->where('is_cover', true)
            ->orderBy('sort_order');
    }

    // Devuelve los servicios del hotel
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'hotel_services')
            ->using(HotelService::class)
            ->withTimestamps();
    }

    // Devuelve los tipos de habitación del hotel
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    // Devuelve las reservas del hotel
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    // Devuelve las reseñas del hotel
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // Devuelve los favoritos del hotel
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
