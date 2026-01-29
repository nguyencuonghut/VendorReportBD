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
        Schema::create('vendor_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->nullable(); // YYYY/DEPT/SEQ (GLOBAL sequence)
            $table->string('title');
            $table->enum('workflow_type', ['NORMAL', 'SPECIAL_1', 'SPECIAL_2', 'SPECIAL_3', 'URGENT']);
            $table->foreignId('purchasing_admin_id')->nullable()->constrained('users')->nullOnDelete(); // CHỈ ĐỂ THEO DÕI, KHÔNG NẰM TRONG WORKFLOW
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'IN_APPROVAL', 'APPROVED', 'REJECTED'])->default('DRAFT');
            // ⚠️ KHÔNG thêm current_step_id ở đây vì circular dependency
            // Sẽ thêm sau khi vendor_report_approval_steps đã tồn tại
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_note')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('vendor_reports')->nullOnDelete();
            $table->foreignId('root_id')->nullable()->constrained('vendor_reports')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
            $table->index('status');
            $table->index('workflow_type');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_reports');
    }
};
