<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title', 120)->nullable();
            $table->string('status', 32)->default('active')->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();

            // string (not enum) so future tool / tool_result roles stay migration-light
            $table->string('role', 32);
            $table->longText('content');
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
