<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user's linked Grinding Gear Games account, so the PoE2 MCP tools can
     * read the characters they actually play.
     *
     * One row per user: linking a second GGG account replaces the first. The
     * tokens are encrypted at rest and never leave the server — they are only
     * ever used server-side against api.pathofexile.com.
     */
    public function up(): void
    {
        Schema::create('poe_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // From GET /profile: the stable account uuid and the display name.
            $table->string('ggg_uuid')->nullable();
            $table->string('ggg_name');

            // Encrypted casts, so these are ciphertext blobs rather than the
            // ~40 characters a raw token would need.
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->jsonb('scopes')->nullable();

            $table->timestamp('connected_at');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poe_accounts');
    }
};
