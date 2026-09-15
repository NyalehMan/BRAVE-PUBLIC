<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cad_calls')) {
            return;
        }

        Schema::table('cad_calls', function (Blueprint $table) {
            if (!Schema::hasColumn('cad_calls', 'call_started_at')) {
                $table->timestamp('call_started_at')->nullable();
            }

            if (!Schema::hasColumn('cad_calls', 'call_ended_at')) {
                $table->timestamp('call_ended_at')->nullable();
            }

            if (!Schema::hasColumn('cad_calls', 'previous_call_count')) {
                $table->unsignedInteger('previous_call_count')->default(0);
            }

            if (!Schema::hasColumn('cad_calls', 'false_alarm_count')) {
                $table->unsignedInteger('false_alarm_count')->default(0);
            }

            if (!Schema::hasColumn('cad_calls', 'suspicious_score')) {
                $table->unsignedInteger('suspicious_score')->default(0);
            }

            if (!Schema::hasColumn('cad_calls', 'prank_flag')) {
                $table->boolean('prank_flag')->default(false);
            }
        });
    }

    public function down(): void
    {
        // Compatibility columns are retained to avoid losing CAD history.
    }
};
