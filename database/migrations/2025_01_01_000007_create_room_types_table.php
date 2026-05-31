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
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('capacity_adults');
            $table->unsignedTinyInteger('capacity_children');
            $table->decimal('size_m2', 6, 2)->nullable();
            $table->string('bed_type', 100)->nullable();
            $table->decimal('base_price', 10, 2);
            $table->char('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('total_units');
            $table->unsignedSmallInteger('free_cancellation_hours')->nullable();
            $table->enum('status', ['active', 'inactive']);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_capacity_adults CHECK (capacity_adults >= 1)');
        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_capacity_children CHECK (capacity_children >= 0)');
        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_size_m2 CHECK (size_m2 IS NULL OR size_m2 >= 0)');
        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_base_price CHECK (base_price >= 0)');
        DB::statement('ALTER TABLE room_types ADD CONSTRAINT chk_room_types_total_units CHECK (total_units >= 1)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
