<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_servers', function (Blueprint $table) {
            $table->id();
            $table->string('server_name')->unique();
            $table->jsonb('role_mappings')->default('[]');
            $table->timestamps();
        });

        Schema::table('discord_channels', function (Blueprint $table) {
            $table->foreignId('discord_server_id')
                ->nullable()
                ->after('id')
                ->constrained('discord_servers')
                ->nullOnDelete();
        });

        $roleMappings = DB::table('discord_channels')
            ->whereNotNull('role_mappings')
            ->where('role_mappings', '!=', '[]')
            ->where('role_mappings', '!=', '{}')
            ->value('role_mappings');

        $serverId = DB::table('discord_servers')->insertGetId([
            'server_name' => 'Default Discord Server',
            'role_mappings' => $roleMappings ?: '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('discord_channels')->update([
            'discord_server_id' => $serverId,
        ]);
    }

    public function down(): void
    {
        Schema::table('discord_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discord_server_id');
        });

        Schema::dropIfExists('discord_servers');
    }
};
