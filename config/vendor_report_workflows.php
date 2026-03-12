<?php

/**
 * Workflow Definitions cho Vendor Report System
 *
 * 5 loại workflows:
 * 1. NORMAL - Phiếu thông thường
 * 2. SPECIAL_1 - Quy trình qua 2 BOD
 * 3. SPECIAL_2 - Quy trình qua Khối Mua Hàng
 * 4. SPECIAL_3 - Quy trình qua Ban Kỹ thuật
 * 5. URGENT - Phiếu gấp (bỏ qua Kiểm soát nội bộ)
 */

return [

    /**
     * NORMAL - Phiếu thông thường
     * Luồng: Người tạo → Trưởng phòng → Kiểm soát nội bộ (chọn BGĐ) → Ban giám đốc
     */
    'NORMAL' => [
        [
            'step_key' => 'DEPT_HEAD',
            'requires_selection' => false,
        ],
        [
            'step_key' => 'INTERNAL_CONTROL',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD',
            'requires_selection' => false,
        ],
    ],

    /**
     * SPECIAL_1 - Quy trình qua 2 BOD
     * Luồng: Người tạo → Trưởng phòng → Kiểm soát nội bộ (chọn BGĐ 1) → BGĐ 1 (chọn BGĐ 2) → BGĐ 2
     */
    'SPECIAL_1' => [
        [
            'step_key' => 'DEPT_HEAD',
            'requires_selection' => false,
        ],
        [
            'step_key' => 'INTERNAL_CONTROL',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD_1',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD_2',
            'requires_selection' => false,
        ],
    ],

    /**
     * SPECIAL_2 - Quy trình qua Khối Mua Hàng
     * Luồng: Người tạo → Trưởng phòng (chọn Khối mua hàng) → Khối mua hàng → Kiểm soát nội bộ (chọn BGĐ) → BGĐ
     */
    'SPECIAL_2' => [
        [
            'step_key' => 'DEPT_HEAD',
            'requires_selection' => true,
            'selection_role' => 'national_purchasing',
        ],
        [
            'step_key' => 'NATIONAL_PURCHASING',
            'requires_selection' => false,
        ],
        [
            'step_key' => 'INTERNAL_CONTROL',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD',
            'requires_selection' => false,
        ],
    ],

    /**
     * SPECIAL_3 - Quy trình qua Ban Kỹ thuật
     * Luồng: Người tạo → Trưởng phòng (chọn Ban kỹ thuật) → Ban kỹ thuật → Kiểm soát nội bộ (chọn BGĐ) → BGĐ
     */
    'SPECIAL_3' => [
        [
            'step_key' => 'DEPT_HEAD',
            'requires_selection' => true,
            'selection_role' => 'tech_board',
        ],
        [
            'step_key' => 'TECH_BOARD',
            'requires_selection' => false,
        ],
        [
            'step_key' => 'INTERNAL_CONTROL',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD',
            'requires_selection' => false,
        ],
    ],

    /**
     * URGENT - Phiếu gấp (Bỏ qua Kiểm soát nội bộ)
     * Luồng: Người tạo → Trưởng phòng (tick gấp + chọn BGĐ) → BGĐ
     */
    'URGENT' => [
        [
            'step_key' => 'DEPT_HEAD',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD',
            'requires_selection' => false,
        ],
    ],

    /**
     * SPECIAL_4 - Phiếu đặc biệt 4 (Bỏ qua Trưởng phòng)
     * Luồng: Người tạo → Kiểm soát nội bộ (chọn BGĐ 1) → BGĐ 1 (chọn BGĐ 2) → BGĐ 2
     * Use case: Phiếu cần xử lý khẩn cấp, bỏ qua bước Trưởng phòng để rút ngắn thời gian
     */
    'SPECIAL_4' => [
        [
            'step_key' => 'INTERNAL_CONTROL',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD_1',
            'requires_selection' => true,
            'selection_role' => 'bod',
        ],
        [
            'step_key' => 'BOD_2',
            'requires_selection' => false,
        ],
    ],

];
