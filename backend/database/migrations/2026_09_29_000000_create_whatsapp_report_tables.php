<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_report_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('sender_wa_id', 32)->unique();
            $table->string('reporter_name', 150)->nullable();
            $table->string('phone_number_id', 32)->nullable();
            $table->string('incident_type', 100)->nullable();
            $table->string('severity_level', 20)->nullable();
            $table->string('district', 80)->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_inbound_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('meta_message_id', 255)->unique();
            $table->string('sender_wa_id', 32)->index();
            $table->string('message_type', 30);
            $table->foreignId('public_incident_report_id')
                ->nullable()
                ->constrained('public_incident_reports')
                ->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound_messages');
        Schema::dropIfExists('whatsapp_report_drafts');
    }
};
