<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cad_calls')) {
            Schema::create('cad_calls', function (Blueprint $table) {
                $table->id();
                $table->string('call_ref', 40)->unique();
                $table->string('caller_phone', 30)->nullable()->index();
                $table->string('caller_name', 150)->nullable();
                $table->string('caller_id_type', 30)->nullable();
                $table->string('caller_id_no', 80)->nullable();
                $table->string('operator_name', 150)->nullable();
                $table->string('status', 40)->default('OPEN')->index();
                $table->timestamp('call_started_at')->nullable();
                $table->timestamp('call_ended_at')->nullable();
                $table->unsignedInteger('previous_call_count')->default(0);
                $table->unsignedInteger('false_alarm_count')->default(0);
                $table->unsignedInteger('suspicious_score')->default(0);
                $table->boolean('prank_flag')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cad_incident_intake')) {
            Schema::create('cad_incident_intake', function (Blueprint $table) {
                $table->id();
                $table->foreignId('call_id')
                    ->constrained('cad_calls')
                    ->cascadeOnDelete();
                $table->string('incident_category', 100);
                $table->string('severity_level', 30);
                $table->string('district', 100)->nullable();
                $table->text('location_description')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('more_details')->nullable();
                $table->string('validation_status', 30)
                    ->default('PENDING')
                    ->index();
                $table->boolean('submitted_to_brave')->default(false);
                $table->unsignedBigInteger('brave_incident_objectid')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cad_validation_answers')) {
            Schema::create('cad_validation_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('intake_id')
                    ->constrained('cad_incident_intake')
                    ->cascadeOnDelete();
                $table->text('question_text');
                $table->text('answer')->nullable();
                $table->integer('risk_score')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cad_audit_logs')) {
            Schema::create('cad_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('action', 80);
                $table->string('entity_type', 100)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index([
                    'entity_type',
                    'entity_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cad_audit_logs');
        Schema::dropIfExists('cad_validation_answers');
        Schema::dropIfExists('cad_incident_intake');
        Schema::dropIfExists('cad_calls');
    }
};
