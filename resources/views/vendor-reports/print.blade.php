<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In phiếu - {{ $report->code }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.2;
            color: #000;
            background: #fff;
        }

        .print-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 8mm;
            background: white;
        }

        /* Header */
        .header {
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .company-name {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .report-code {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }

        .report-title-section {
            text-align: center;
            margin-bottom: 6px;
        }

        .report-title-section h1 {
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .report-title-section .title-value {
            font-size: 11px;
            font-weight: normal;
            color: #333;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 12px;
            margin-bottom: 8px;
            font-size: 9px;
        }

        .info-item {
            display: flex;
        }

        .info-label {
            font-weight: bold;
            min-width: 80px;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
        }

        .status-badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 2px;
            font-weight: bold;
            font-size: 9px;
        }

        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-in-approval { background: #fff3cd; color: #856404; }
        .status-draft { background: #d1ecf1; color: #0c5460; }

        /* Approval Steps */
        .approval-section {
            margin-bottom: 8px;
        }

        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid #333;
        }

        .approval-steps {
            display: grid;
            gap: 4px;
        }

        .approval-step {
            border: 1px solid #ddd;
            padding: 4px;
            border-radius: 2px;
            background: #f9f9f9;
            font-size: 8px;
        }

        .step-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .step-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px 6px;
            font-size: 8px;
        }

        .step-note {
            margin-top: 3px;
            padding: 3px;
            background: #fff;
            border-left: 2px solid #007bff;
            font-size: 8px;
            font-style: italic;
        }

        /* Files Section */
        .files-section {
            margin-bottom: 8px;
        }

        .files-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .file-item {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 2px;
            overflow: hidden;
            background: #f9f9f9;
            page-break-inside: avoid;
        }

        /* Ảnh dài tự động xuống trang mới */
        .file-item.long-image {
            page-break-before: always;
        }

        .file-image {
            width: 100%;
            height: auto;
            min-height: 300px;
            max-height: 500px;
            object-fit: contain;
            background: #fff;
        }

        .file-info {
            padding: 3px;
            font-size: 7px;
            text-align: center;
            background: #f9f9f9;
        }

        .file-name {
            font-weight: bold;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Rejected Note */
        .rejected-note {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 6px;
            border-radius: 2px;
            margin-bottom: 8px;
        }

        .rejected-note-title {
            font-weight: bold;
            color: #721c24;
            margin-bottom: 3px;
            font-size: 9px;
        }

        .rejected-note-content {
            color: #721c24;
            font-size: 8px;
        }

        /* Footer */
        .footer {
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            text-align: center;
            color: #666;
        }

        /* Print Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .print-container {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 8mm;
            }

            .no-print {
                display: none !important;
            }

            /* Tối ưu để fit trong 1 trang */
            .approval-step {
                page-break-inside: avoid;
            }

            .file-item {
                page-break-inside: avoid;
                width: 100%;
            }

            .file-image {
                max-height: 260mm;
            }

            .file-item.long-image .file-image {
                max-height: 270mm;
            }
        }

        @page {
            size: A4;
            margin: 10mm;
        }

        /* Print Button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .print-button:hover {
            background: #0056b3;
        }

        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ In phiếu</button>

    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div class="company-name">HỆ THỐNG QUẢN LÝ BÁO CÁO NHÀ CUNG CẤP</div>
                <div class="report-code">{{ $report->code }}</div>
            </div>
            <div class="report-title-section">
                <h1>PHIẾU BÁO CÁO NHÀ CUNG CẤP</h1>
                <div class="title-value">{{ $report->title }}</div>
            </div>
        </div>

        <!-- Basic Info -->
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Người tạo:</span>
                <span class="info-value">{{ $report->creator->name }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Phòng ban:</span>
                <span class="info-value">{{ $report->creator->department->name ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Ngày tạo:</span>
                <span class="info-value">{{ $report->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Quy trình:</span>
                <span class="info-value">{{ $report->getWorkflowTypeLabel() }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Trạng thái:</span>
                <span class="info-value">
                    <span class="status-badge status-{{ strtolower(str_replace('_', '-', $report->status)) }}">
                        {{ $report->getStatusLabel() }}
                    </span>
                </span>
            </div>
            @if($report->submitted_at)
            <div class="info-item">
                <span class="info-label">Ngày gửi:</span>
                <span class="info-value">{{ $report->submitted_at->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            @if($report->approved_at)
            <div class="info-item">
                <span class="info-label">Ngày duyệt:</span>
                <span class="info-value">{{ $report->approved_at->format('d/m/Y H:i') }}</span>
            </div>
            @endif
            @if($report->rejected_at)
            <div class="info-item">
                <span class="info-label">Ngày từ chối:</span>
                <span class="info-value">{{ $report->rejected_at->format('d/m/Y H:i') }}</span>
            </div>
            @endif
        </div>

        <!-- Rejected Note (if any) -->
        @if($report->status === 'REJECTED' && $report->rejected_note)
        <div class="rejected-note">
            <div class="rejected-note-title">Lý do từ chối:</div>
            <div class="rejected-note-content">{{ $report->rejected_note }}</div>
        </div>
        @endif

        <!-- Approval Steps -->
        @if($report->approvalSteps->isNotEmpty())
        <div class="approval-section">
            <div class="section-title">Quá trình phê duyệt</div>
            <div class="approval-steps">
                @foreach($report->approvalSteps as $step)
                <div class="approval-step">
                    <div class="step-header">
                        <span>Bước {{ $step->step_order }}: {{ $step->getStepKeyLabel() }}</span>
                        <span class="status-badge status-{{ strtolower(str_replace('_', '-', $step->status)) }}">
                            {{ $step->getStatusLabel() }}
                        </span>
                    </div>
                    <div class="step-info">
                        @if($step->assignee)
                        <div><strong>Người được giao:</strong> {{ $step->assignee->name }}</div>
                        @elseif($step->assignee_role)
                        <div><strong>Vai trò:</strong> {{ $step->assignee_role }}</div>
                        @endif

                        @if($step->actedBy)
                        <div><strong>Người xử lý:</strong> {{ $step->actedBy->name }}</div>
                        @endif

                        @if($step->acted_at)
                        <div><strong>Thời gian:</strong> {{ $step->acted_at->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>

                    @if($step->note)
                    <div class="step-note">
                        <strong>Ghi chú:</strong> {{ $step->note }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Files Section (Only REPORT_IMAGE) -->
        @php
            $reportImages = $report->files->where('type', 'REPORT_IMAGE');
        @endphp

        @if($reportImages->isNotEmpty())
        <div class="files-section">
            <div class="section-title">Hình ảnh báo cáo ({{ $reportImages->count() }})</div>
            <div class="files-grid">
                @foreach($reportImages as $file)
                <div class="file-item">
                    @if(str_starts_with($file->mime, 'image/'))
                        <img src="{{ route('vendor-reports.files.view', $file->id) }}"
                             alt="{{ $file->original_name }}"
                             class="file-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div style="display:none; min-height:200px; align-items:center; justify-content:center; background:#e9ecef; font-size:10px;">
                            📷 Không thể tải hình ảnh
                        </div>
                    @else
                        <div style="min-height:200px; display:flex; align-items:center; justify-content:center; background:#e9ecef; font-size:10px;">
                            📄 {{ strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION)) }}
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div>In lúc: {{ now()->format('d/m/Y H:i:s') }}</div>
            <div>Người in: {{ auth()->user()->name }}</div>
        </div>
    </div>

    <script>
        // Phát hiện ảnh dài và tự động xuống trang mới
        window.addEventListener('load', function() {
            const images = document.querySelectorAll('.file-image');

            images.forEach(img => {
                // Đợi ảnh load xong mới đo kích thước
                if (img.complete) {
                    checkImageHeight(img);
                } else {
                    img.addEventListener('load', function() {
                        checkImageHeight(img);
                    });
                }
            });
        });

        function checkImageHeight(img) {
            // Nếu chiều cao ảnh > 1200px (tương đương ~210mm),
            // có thể không vừa với thông tin duyệt trên 1 trang
            if (img.naturalHeight > 1200) {
                const fileItem = img.closest('.file-item');
                if (fileItem) {
                    fileItem.classList.add('long-image');
                }
            }
        }

        // Auto print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
