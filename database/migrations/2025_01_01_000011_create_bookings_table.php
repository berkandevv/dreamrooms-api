<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_reference', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('hotel_id')->constrained('hotels')->restrictOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->restrictOnDelete();
            $table->string('hotel_name', 180);
            $table->string('room_type_name', 120);
            $table->string('customer_name', 150);
            $table->string('customer_email', 150);
            $table->string('customer_phone', 30)->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('nights');
            $table->unsignedTinyInteger('adults_count');
            $table->unsignedTinyInteger('children_count');
            $table->unsignedSmallInteger('units_booked');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed']);
            $table->enum('payment_method', ['card', 'hotel'])->default('hotel');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded']);
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('taxes_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->dateTime('booked_at');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_dates CHECK (check_in < check_out)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_nights CHECK (nights >= 1)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_adults_count CHECK (adults_count >= 1)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_children_count CHECK (children_count >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_units_booked CHECK (units_booked >= 1)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_subtotal_amount CHECK (subtotal_amount >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_taxes_amount CHECK (taxes_amount >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_discount_amount CHECK (discount_amount >= 0)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT chk_bookings_total_amount CHECK (total_amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
