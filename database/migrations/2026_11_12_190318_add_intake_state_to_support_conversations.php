<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            // Other exercises leave this null; intake uses active/completed/abandoned.
            $table->string('intake_status', 16)->nullable();
            $table->index(['user_id', 'agent_driver', 'intake_status'], 'support_intake_lookup_index');
        });
        Schema::table('technical_support_issues', function (Blueprint $table): void {
            // One completed intake can produce at most one ticket.
            $table->foreignId('support_conversation_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('technical_support_issues', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('support_conversation_id');
        });
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropIndex('support_intake_lookup_index');
            $table->dropColumn('intake_status');
        });
    }
};
