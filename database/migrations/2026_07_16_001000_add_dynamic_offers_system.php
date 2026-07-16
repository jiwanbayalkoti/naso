<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('audience', 20); // shop|rider
            $table->string('type', 50);
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('window', 30)->default('calendar_month'); // lifetime|calendar_month
            $table->json('config')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['audience', 'is_active', 'priority']);
        });

        Schema::create('offer_redemptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('offer_id')->constrained('offers')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->json('benefit')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'shop_id']);
            $table->index(['offer_id', 'rider_id']);
            $table->index(['delivery_id']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('base_delivery_fee', 10, 2)->nullable()->after('delivery_fee');
            $table->json('applied_offer_ids')->nullable()->after('base_delivery_fee');
            $table->string('offer_notes', 500)->nullable()->after('applied_offer_ids');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['base_delivery_fee', 'applied_offer_ids', 'offer_notes']);
        });

        Schema::dropIfExists('offer_redemptions');
        Schema::dropIfExists('offers');
    }
};
