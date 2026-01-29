<?php

namespace App\Services;

use App\Models\YearlySequence;
use App\Models\Department;
use Illuminate\Support\Facades\DB;

class VendorReportCodeGenerator
{
    /**
     * Generate code cho vendor report: YYYY/DEPT_CODE/SEQ
     * 
     * Sequence là GLOBAL cho tất cả phòng ban
     * Ví dụ: 2026/TM/024 → 2026/BT/025 → 2026/KSNB/026
     *
     * @param int $departmentId
     * @return string
     */
    public function generate(int $departmentId): string
    {
        return DB::transaction(function () use ($departmentId) {
            $year = now()->year;
            
            // Lấy department code
            $department = Department::findOrFail($departmentId);
            $deptCode = $department->code;
            
            // Lấy hoặc tạo yearly sequence (GLOBAL - không có department_id)
            $yearlySeq = YearlySequence::lockForUpdate()
                ->firstOrCreate(
                    ['year' => $year],
                    ['current_seq' => 0]
                );
            
            // Tăng sequence
            $yearlySeq->increment('current_seq');
            $seq = $yearlySeq->current_seq;
            
            // Format: YYYY/DEPT_CODE/SEQ (SEQ padding 3 digits)
            return sprintf('%d/%s/%03d', $year, $deptCode, $seq);
        });
    }
    
    /**
     * Reset sequence cho năm mới (chạy bằng schedule hoặc manual)
     *
     * @param int|null $year
     * @return void
     */
    public function resetSequence(?int $year = null): void
    {
        $year = $year ?? now()->year;
        
        YearlySequence::updateOrCreate(
            ['year' => $year],
            ['current_seq' => 0]
        );
    }
}
