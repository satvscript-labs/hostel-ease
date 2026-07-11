<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — automatic volume-discount tiers (BR-26). Applied by quantity
 * (branch count). Seeded empty; the Super Admin adds tiers in Settings so no
 * discount is ever applied by surprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_quantity');     // applies when quantity >= this
            $table->string('type');                      // DiscountType
            $table->decimal('value', 12, 2);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->index(['active', 'min_quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
