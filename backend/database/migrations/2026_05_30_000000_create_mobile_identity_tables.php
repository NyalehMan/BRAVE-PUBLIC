<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('brunei_identities')) {
            Schema::create('brunei_identities', function (Blueprint $table) {
                $table->id();
                $table->string('ic_no', 20)->unique();
                $table->string('full_name', 150);
                $table->text('address')->nullable();
                $table->string('nationality', 80)->nullable();
                $table->string('id_picture')->nullable();
                $table->string('password');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('mobile_app_users')) {
            Schema::create('mobile_app_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brunei_identity_id')
                    ->constrained('brunei_identities')
                    ->cascadeOnDelete();
                $table->string('device_id', 191);
                $table->char('api_token', 64)->unique();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();

                $table->index([
                    'brunei_identity_id',
                    'device_id',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_users');
        Schema::dropIfExists('brunei_identities');
    }
};
