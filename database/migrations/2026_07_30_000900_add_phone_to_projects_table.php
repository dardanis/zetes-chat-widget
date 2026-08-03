<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // E.164, e.g. +38344123456. The unique index is what keeps inbound routing safe:
            // two projects can never claim the same number and silently cross-wire tenants.
            $table->string('phone_number')->nullable()->unique()->after('widget_settings');
            $table->string('twilio_phone_sid')->nullable()->after('phone_number');
            $table->json('voice_settings')->nullable()->after('twilio_phone_sid');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique(['phone_number']);
            $table->dropColumn(['phone_number', 'twilio_phone_sid', 'voice_settings']);
        });
    }
};
