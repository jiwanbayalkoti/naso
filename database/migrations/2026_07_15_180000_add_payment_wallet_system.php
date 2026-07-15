<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->default(0)->after('nid_number');
            $table->string('bank_name')->nullable()->after('balance');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->default(0)->after('total_deliveries');
            $table->string('bank_name')->nullable()->after('balance');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('distance_km', 8, 2)->nullable()->after('delivery_fee');
            $table->decimal('cod_amount', 12, 2)->default(0)->after('distance_km');
            $table->decimal('rider_earning', 10, 2)->nullable()->after('cod_amount');
            $table->decimal('platform_commission', 10, 2)->nullable()->after('rider_earning');
            $table->timestamp('settled_at')->nullable()->after('completed_at');
            $table->timestamp('cod_collected_at')->nullable()->after('settled_at');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['rider_id', 'created_at']);
            $table->index(['type']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('wallet_transactions');

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'distance_km',
                'cod_amount',
                'rider_earning',
                'platform_commission',
                'settled_at',
                'cod_collected_at',
            ]);
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['balance', 'bank_name', 'bank_account_name', 'bank_account_number']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['balance', 'bank_name', 'bank_account_name', 'bank_account_number']);
        });
    }
};
