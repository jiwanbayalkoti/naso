<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('password');
            $table->foreignId('created_by')->nullable()->after('remember_token')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index('phone');
            $table->index('is_active');
        });

        DB::table('users')->whereNull('uuid')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);

            $table->dropIndex(['phone']);
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'uuid',
                'phone',
                'avatar',
                'is_active',
                'created_by',
                'updated_by',
                'deleted_by',
                'deleted_at',
            ]);
        });
    }
};
