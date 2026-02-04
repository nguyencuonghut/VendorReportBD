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
        Schema::create('vendor_report_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('vendor_reports')->cascadeOnDelete();
            $table->string('step_key'); // DEPT_HEAD, INTERNAL_CONTROL, BOD, etc.
            $table->integer('step_order');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'SKIPPED'])->default('PENDING');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_role')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->text('note')->nullable();
            $table->boolean('requires_selection')->default(false);
            $table->string('selection_role')->nullable();
            $table->foreignId('selected_next_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Single column indexes
            $table->index('report_id');
            $table->index('step_order');
            $table->index('status');
            $table->index('assignee_user_id');

            // Composite indexes for common queries
            $table->index(['report_id', 'status', 'step_order']); // Get current step
            $table->index(['assignee_user_id', 'status']); // Find user's pending approvals
            $table->index(['status', 'created_at']); // Pending steps by date
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_report_approval_steps');
    }
};
