<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('resources_limit')->nullable()->after('features');
            $table->unsignedInteger('projects_limit')->nullable()->after('resources_limit');
            $table->unsignedInteger('bandwidth_limit')->nullable()->comment('in GB')->after('projects_limit');
            $table->unsignedInteger('storage_limit')->nullable()->comment('in GB')->after('bandwidth_limit');
            $table->unsignedInteger('team_members_limit')->nullable()->after('storage_limit');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'resources_limit',
                'projects_limit',
                'bandwidth_limit',
                'storage_limit',
                'team_members_limit',
            ]);
        });
    }
};
