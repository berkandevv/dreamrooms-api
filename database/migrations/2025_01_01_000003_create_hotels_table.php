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
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 180);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('stars');
            $table->string('country', 100);
            $table->string('region', 100)->nullable();
            $table->string('city', 100);
            $table->string('address', 255);
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->decimal('discount_rate_percent', 5, 2)->default(0);
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('smoking_allowed')->default(false);
            $table->enum('status', ['draft', 'published', 'inactive']);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE hotels ADD CONSTRAINT chk_hotels_stars CHECK (stars BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE hotels ADD CONSTRAINT chk_hotels_tax_rate_percent CHECK (tax_rate_percent BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE hotels ADD CONSTRAINT chk_hotels_discount_rate_percent CHECK (discount_rate_percent BETWEEN 0 AND 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
