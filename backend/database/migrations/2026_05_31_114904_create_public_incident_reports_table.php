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
        Schema::create('public_incident_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mobile_app_user_id')
                ->nullable()
                ->constrained('mobile_app_users')
                ->nullOnDelete();

            $table->foreignId('brunei_identity_id')
                ->nullable()
                ->constrained('brunei_identities')
                ->nullOnDelete();

            $table->string('reporter_ic_no', 20);
            $table->string('reporter_full_name', 150);

            $table->string('district', 80);
            $table->string('incident_type', 100);
            $table->text('description');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('photo_path')->nullable();

            $table->enum('status', [
                'PENDING',
                'VERIFIED',
                'NEEDS_VERIFY',
                'REJECTED',
            ])->default('PENDING');

            $table->text('operator_notes')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_incident_reports');
    }
};
