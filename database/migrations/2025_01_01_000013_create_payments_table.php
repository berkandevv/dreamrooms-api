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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('provider', ['stripe', 'paypal', 'manual']);
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3);
            $table->enum('status', ['pending', 'authorized', 'paid', 'failed', 'refunded', 'partially_refunded']);
            $table->string('transaction_reference', 100)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
