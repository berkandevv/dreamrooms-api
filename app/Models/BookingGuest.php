<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingGuest extends Model
{
    protected $fillable = [
        'booking_id',
        'full_name',
        'document_type',
        'document_number',
        'birth_date',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
