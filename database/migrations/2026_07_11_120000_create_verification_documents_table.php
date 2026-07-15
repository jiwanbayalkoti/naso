<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('documentable');
            $table->string('type', 50);
            $table->string('document_number')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documentable_type', 'documentable_id', 'type'], 'verification_docs_owner_type_unique');
            $table->index('type');
            $table->index('status');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('pan_number', 50)->nullable()->after('description');
            $table->string('nid_number', 50)->nullable()->after('pan_number');
        });

        Schema::table('riders', function (Blueprint $table) {
            $table->string('pan_number', 50)->nullable()->after('license_number');
            $table->string('nid_number', 50)->nullable()->after('pan_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['pan_number', 'nid_number']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['pan_number', 'nid_number']);
        });

        Schema::dropIfExists('verification_documents');
    }
};
