<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->float('storage_usage_gb')->default(0)->after('stripe_past_due');
            $table->float('bandwidth_usage_gb')->default(0)->after('storage_usage_gb');
            $table->timestamp('usage_synced_at')->nullable()->after('bandwidth_usage_gb');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['storage_usage_gb', 'bandwidth_usage_gb', 'usage_synced_at']);
        });
    }
};
