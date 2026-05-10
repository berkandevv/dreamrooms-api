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
        Schema::create('room_type_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('available_units');
            $table->decimal('price', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->enum('status', ['open', 'closed']);
            $table->unsignedSmallInteger('min_stay_nights')->nullable();
            $table->timestamps();
            $table->unique(['room_type_id', 'date']);
        });

        DB::statement('ALTER TABLE room_type_availabilities ADD CONSTRAINT chk_rta_available_units CHECK (available_units >= 0)');
        DB::statement('ALTER TABLE room_type_availabilities ADD CONSTRAINT chk_rta_price CHECK (price >= 0)');
        DB::statement('ALTER TABLE room_type_availabilities ADD CONSTRAINT chk_rta_min_stay_nights CHECK (min_stay_nights IS NULL OR min_stay_nights >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_type_availabilities');
    }
};
