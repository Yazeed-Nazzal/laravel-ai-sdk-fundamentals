<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technical_support_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('category');
            // A reported ID is an unverified claim, not permission to access an order.
            $table->unsignedBigInteger('reported_order_id')->nullable();
            $table->string('priority');
            $table->boolean('requires_human');
            $table->string('status')->default('open');
            $table->timestamp('priority_escalated_at');
            $table->timestamps();
            $table->index(['status', 'priority', 'priority_escalated_at'], 'support_issue_escalation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_support_issues');
    }
};
