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
        if (!Schema::hasTable('public_incident_reports')) {
            return;
        }

        Schema::table('public_incident_reports', function (Blueprint $table) {
            if (!Schema::hasColumn(
                'public_incident_reports',
                'submitted_to_brave'
            )) {
                $table->boolean('submitted_to_brave')
                    ->default(false)
                    ->after('status');
            }

            if (!Schema::hasColumn(
                'public_incident_reports',
                'brave_incident_objectid'
            )) {
                $table->unsignedBigInteger('brave_incident_objectid')
                    ->nullable()
                    ->after('submitted_to_brave');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('public_incident_reports')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn(
                'public_incident_reports',
                'submitted_to_brave'
            ) ? 'submitted_to_brave' : null,

            Schema::hasColumn(
                'public_incident_reports',
                'brave_incident_objectid'
            ) ? 'brave_incident_objectid' : null,
        ]));

        if ($columns !== []) {
            Schema::table(
                'public_incident_reports',
                fn (Blueprint $table) => $table->dropColumn($columns)
            );
        }
    }
};
