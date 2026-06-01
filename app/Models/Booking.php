<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'user_id',
        'hotel_id',
        'room_type_id',
        'hotel_name',
        'room_type_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'check_in',
        'check_out',
        'nights',
        'adults_count',
        'children_count',
        'units_booked',
        'status',
        'payment_method',
        'payment_status',
        'subtotal_amount',
        'taxes_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'booked_at',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_deadline_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'booked_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_deadline_at' => 'datetime',
            'subtotal_amount' => 'decimal:2',
            'taxes_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    // Filtra las reservas de los hoteles de un propietario concreto
    public function scopeOwnedBy(Builder $query, int $ownerUserId): Builder
    {
        return $query->whereHas('hotel', fn ($hotelQuery) => $hotelQuery->ownedBy($ownerUserId));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(BookingGuest::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    // Indica si el cliente todavía puede cancelar según la política contratada
    public function canBeCancelledByCustomer(): bool
    {
        return ! in_array($this->status, ['cancelled', 'completed'], true)
            && ($this->cancellation_deadline_at === null || $this->cancellation_deadline_at->isFuture());
    }
}
