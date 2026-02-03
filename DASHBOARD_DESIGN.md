# THIẾT KẾ DASHBOARD - HỆ THỐNG QUẢN LÝ BÁO CÁO LỰA CHỌN NHÀ CUNG CẤP

> **Dựa trên**: Source code hiện có, Sakai Vue Template, PrimeVue v4, Inertia.js

## 1. Tổng quan Dashboard

### 1.1 Tech Stack hiện có
- **Backend**: Laravel 11+ với Inertia.js
- **Frontend**: Vue 3 Composition API
- **UI Framework**: PrimeVue v4 (Aura theme)
- **Template**: Sakai Vue (AppLayout.vue)
- **Styling**: SCSS + Tailwind CSS
- **Services**: VendorReportSubmissionService, VendorReportApprovalService
- **Resources**: VendorReportResource, UserResource

### 1.2 Nguyên tắc thiết kế
- **Tích hợp với AppLayout hiện có**: Dùng layout Sakai Vue
- **Role-based content**: Sử dụng dữ liệu từ VendorReportResource
- **Consistent styling**: Giống Index.vue, Show.vue (card, Toolbar, DataTable)
- **Reuse components**: Toast, ConfirmationService, PrimeVue components
- **Real-time metrics**: API endpoints trả về thống kê

### 1.3 Layout Dashboard (Trong AppLayout)
```
┌─────────────────────────────────────────────────────────────┐
│ AppTopbar (Logo | Navigation | User Menu)                    │
├──────┬──────────────────────────────────────────────────────┤
│ App  │ <div class="card"> (PrimeVue Card wrapper)           │
│ Side │   <h3>Xin chào, [User Name]</h3>                     │
│ bar  │   <p>[Department] - [Roles]</p>                      │
│      │                                                        │
│      │   <!-- Metrics Grid -->                               │
│      │   <div class="grid">                                  │
│      │     <div class="col-12 md:col-6 lg:col-3">          │
│      │       <div class="card surface-card shadow-2">       │
│      │         [Metric Card 1]                               │
│      │       </div>                                          │
│      │     </div>                                            │
│      │     ... (4 metrics)                                   │
│      │   </div>                                              │
│      │                                                        │
│      │   <!-- Main Content -->                               │
│      │   <div class="grid">                                  │
│      │     <div class="col-12 lg:col-8">                    │
│      │       <div class="card">                              │
│      │         <DataTable> Pending Actions </DataTable>      │
│      │       </div>                                          │
│      │       <div class="card mt-4">                         │
│      │         <Chart> Analytics </Chart>                    │
│      │       </div>                                          │
│      │     </div>                                            │
│      │     <div class="col-12 lg:col-4">                    │
│      │       <div class="card">                              │
│      │         Quick Actions Grid                            │
│      │       </div>                                          │
│      │       <div class="card mt-4">                         │
│      │         <Timeline> Activities </Timeline>             │
│      │       </div>                                          │
│      │     </div>                                            │
│      │   </div>                                              │
│      │ </div>                                                │
│      │ AppFooter                                             │
└──────┴──────────────────────────────────────────────────────┘
```

---

## 2. Dashboard Components theo Role

> **Data Source**: VendorReport model, VendorReportResource, User permissions

### 2.1 ADMIN_SYSTEM (Quản trị hệ thống)

#### Top Metrics Cards (4 cards grid)
**Query Data:**
```php
// DashboardController sẽ gọi DashboardService
$metrics = [
    'total_reports' => VendorReport::count(),
    'active_users' => User::where('is_active', true)->count(),
    'active_departments' => Department::where('is_active', true)->count(),
    'pending_stuck' => VendorReport::where('status', 'IN_APPROVAL')
        ->where('submitted_at', '<', now()->subDays(7))
        ->count(),
];
```

**Cards:**
1. **Tổng số phiếu**
   - Icon: `pi pi-file`
   - Value: `$metrics['total_reports']`
   - Trend: `+5% so với tháng trước`
   - onClick: `router.visit('/vendor-reports')`
   - Style: `bg-blue-100 text-blue-600`

2. **Người dùng hoạt động**
   - Icon: `pi pi-users`
   - Value: `$metrics['active_users']`
   - Subtitle: `/ ${totalUsers} tổng số`
   - onClick: `router.visit('/admin/users')`
   - Style: `bg-green-100 text-green-600`

3. **Phòng ban hoạt động**
   - Icon: `pi pi-building`
   - Value: `$metrics['active_departments']`
   - onClick: `router.visit('/admin/departments')`
   - Style: `bg-purple-100 text-purple-600`

4. **Phiếu cần xử lý**
   - Icon: `pi pi-exclamation-triangle`
   - Value: `$metrics['pending_stuck']`
   - Subtitle: `> 7 ngày chưa duyệt`
   - Severity: `danger` nếu > 5
   - onClick: Filter phiếu stuck
   - Style: `bg-red-100 text-red-600`

#### Pending Actions Table
**Data Source:**
```php
// Phiếu stuck trong workflow
VendorReport::with(['creator', 'currentStep.assignee', 'department'])
    ->where('status', 'IN_APPROVAL')
    ->where('submitted_at', '<', now()->subDays(5))
    ->get();
```

**Component:** PrimeVue DataTable (style giống Index.vue)

**Columns:**
- Code (link, badge URGENT nếu có)
- Title
- Workflow (Tag với severity)
- Current Step (Step key label)
- Assignee
- Days Pending (Badge với color: danger>5, warn>3)
- Actions (View, Cancel)

**Style Example:**

```html
<DataTable
    :value="stuckReports"
    :paginator="true"
    :rows="10"
    stripedRows
    class="p-datatable-sm"
/>
```

---

#### Charts Section (2 columns grid)

**Charts:**

1. **Phiếu theo trạng thái** (Doughnut Chart - Left)
```javascript
// API: GET /api/dashboard/chart-data?type=status
{
  labels: ['DRAFT', 'IN_APPROVAL', 'APPROVED', 'REJECTED', 'CANCELED'],
  datasets: [{
    data: [10, 25, 150, 15, 5],
    backgroundColor: ['#94a3b8', '#3b82f6', '#22c55e', '#ef4444', '#6b7280']
  }]
}
```

2. **Phiếu theo workflow** (Bar Chart - Right)
```javascript
// API: GET /api/dashboard/chart-data?type=workflow
{
  labels: ['NORMAL', 'SPECIAL_1', 'SPECIAL_2', 'SPECIAL_3', 'URGENT'],
  datasets: [{
    data: [65, 30, 25, 20, 15],
    backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#64748b', '#ef4444']
  }]
}
```

3. **Trend 6 tháng** (Line Chart - Full width)
```javascript
// API: GET /api/dashboard/chart-data?type=trend&period=6months
{
  labels: ['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'],
  datasets: [
    { label: 'Approved', data: [12, 19, 15, 25, 22, 30], borderColor: '#22c55e' },
    { label: 'Rejected', data: [2, 3, 2, 5, 3, 4], borderColor: '#ef4444' }
  ]
}
```

#### Quick Actions Grid
**Component:** Button Grid (2 columns mobile, 4 desktop)
```html
<div class="grid">
  <div class="col-6 md:col-3" v-for="action in quickActions">
    <Button
      :label="action.label"
      :icon="action.icon"
      outlined
      class="w-full"
      @click="router.visit(action.route)"
    />
  </div>
</div>
```

**Actions:**
- Tạo phiếu mới (`/vendor-reports/create`)
- Quản lý users (`/admin/users`)
- Quản lý phòng ban (`/admin/departments`)
- Nhật ký hệ thống (`/activity-logs`)

#### Recent Activities
**Data Source:**
```php
Activity::with('causer', 'subject')
    ->where('log_name', 'vendor_report')
    ->orderByDesc('created_at')
    ->limit(15)
    ->get();
```

**Component:** PrimeVue Timeline (giống Show.vue approval steps)
```html
<Timeline :value="activities" align="left">
  <template #marker="{ item }">
    <i :class="getActivityIcon(item.event)" 
       class="text-white p-2 rounded-full"
       :style="{ backgroundColor: getActivityColor(item.event) }">
    </i>
  </template>
  <template #content="{ item }">
    <small class="text-muted">{{ formatTimeAgo(item.created_at) }}</small>
    <p>
      <strong>{{ item.causer.name }}</strong> {{ item.description }}
      <a :href="`/vendor-reports/${item.subject_id}`" class="text-primary">
        {{ item.subject.code }}
      </a>
    </p>
  </template>
</Timeline>
```

---

### 2.2 REQUESTER (Người tạo phiếu)

#### Top Metrics Cards
**Query Data:**
```php
$userId = auth()->id();
$metrics = [
    'my_total' => VendorReport::where('created_by', $userId)->count(),
    'my_in_approval' => VendorReport::where('created_by', $userId)
        ->where('status', 'IN_APPROVAL')->count(),
    'my_approved' => VendorReport::where('created_by', $userId)
        ->where('status', 'APPROVED')->count(),
    'my_rejected' => VendorReport::where('created_by', $userId)
        ->where('status', 'REJECTED')->count(),
];
```

**Cards:**
1. **Phiếu của tôi**
   - Icon: `pi pi-file-edit`
   - Value: `$metrics['my_total']`
   - Style: `bg-blue-100`

2. **Đang chờ duyệt**
   - Icon: `pi pi-clock`
   - Value: `$metrics['my_in_approval']`
   - Badge severity: `warning`
   - Style: `bg-orange-100`

3. **Đã duyệt**
   - Icon: `pi pi-check-circle`
   - Value: `$metrics['my_approved']`
   - Style: `bg-green-100`

4. **Bị từ chối**
   - Icon: `pi pi-times-circle`
   - Value: `$metrics['my_rejected']`
   - Style: `bg-red-100`
   - Badge: Show "Cần xử lý" nếu > 0

#### Pending Actions Table
**Data:** Phiếu DRAFT của tôi
```php
VendorReport::where('created_by', $userId)
    ->where('status', 'DRAFT')
    ->with(['creator', 'department'])
    ->orderByDesc('updated_at')
    ->get();
```

**Columns:**
- Code
- Title
- Workflow Type (Tag)
- Updated At
- Actions: Edit | Submit | Delete

#### Charts
1. **Phiếu của tôi theo trạng thái** (Doughnut)
2. **Thời gian duyệt trung bình** (Metric Card)
```php
// Average days from submitted_at to approved_at
$avgDays = VendorReport::where('created_by', $userId)
    ->where('status', 'APPROVED')
    ->whereNotNull('submitted_at')
    ->whereNotNull('approved_at')
    ->get()
    ->map(fn($r) => $r->submitted_at->diffInDays($r->approved_at))
    ->avg();
```
3. **Tỷ lệ duyệt** (ProgressBar component)

#### Quick Actions
- Tạo phiếu mới
- Phiếu nháp
- Phiếu đang duyệt
- Tất cả phiếu của tôi

#### Activities
- Chỉ activities của phiếu do tôi tạo

---

### 2.3 PURCHASING_ADMIN (Quản trị mua hàng)

#### Top Metrics Cards
```php
$metrics = [
    'all_reports' => VendorReport::count(),
    'in_approval' => VendorReport::where('status', 'IN_APPROVAL')->count(),
    'my_supervised' => VendorReport::where('purchasing_admin_id', $userId)->count(),
    'need_attention' => VendorReport::where('status', 'IN_APPROVAL')
        ->where('submitted_at', '<', now()->subDays(5))->count(),
];
```

**Cards:**
1. **Tổng phiếu**: Icon `pi pi-chart-bar`
2. **Đang duyệt**: Icon `pi pi-hourglass`
3. **Phiếu theo dõi**: Icon `pi pi-eye`, filter `purchasing_admin_id = me`
4. **Cần chú ý**: Icon `pi pi-exclamation-triangle`, >5 days pending

#### Pending Actions
**Data:** Phiếu có `purchasing_admin_id = $userId` và `status = IN_APPROVAL`

#### Charts
1. **Phiếu theo workflow** (Bar)
2. **Phiếu theo phòng ban** (Horizontal Bar - Top 10)
3. **Trend 6 tháng** (Line)
4. **Average approval time by workflow** (Bar với comparison)

#### Quick Actions
- Tạo phiếu mới
- Báo cáo tổng hợp
- Export Excel
- Phân tích workflow

---

### 2.4 Approvers (INTERNAL_CONTROL, BOD, DEPT_HEAD, etc.)

#### Top Metrics Cards
```php
// Phiếu đang chờ tôi duyệt
$pendingApprovals = VendorReportApprovalStep::where('assignee_user_id', $userId)
    ->where('status', 'PENDING')
    ->count();

// Đã duyệt hôm nay
$approvedToday = VendorReportApprovalStep::where('acted_by', $userId)
    ->where('status', 'APPROVED')
    ->whereDate('acted_at', today())
    ->count();

// Tổng đã từ chối
$totalRejected = VendorReportApprovalStep::where('acted_by', $userId)
    ->where('status', 'REJECTED')
    ->count();

// Avg response time
$avgResponseTime = VendorReportApprovalStep::where('acted_by', $userId)
    ->whereNotNull('acted_at')
    ->get()
    ->map(fn($s) => $s->created_at->diffInHours($s->acted_at) / 24)
    ->avg();
```

**Cards:**
1. **Chờ tôi duyệt**: Badge severity `danger`, icon `pi pi-bell`
2. **Duyệt hôm nay**: Icon `pi pi-check`
3. **Đã từ chối**: Icon `pi pi-times`
4. **Thời gian phản hồi TB**: Icon `pi pi-stopwatch`, unit `days`

#### Pending Actions Table (⭐ PRIORITY #1)
**Data:**
```php
VendorReportApprovalStep::with(['report.creator', 'report.department'])
    ->where('assignee_user_id', $userId)
    ->where('status', 'PENDING')
    ->join('vendor_reports', 'vendor_report_approval_steps.report_id', '=', 'vendor_reports.id')
    ->orderByRaw("CASE WHEN vendor_reports.workflow_type = 'URGENT' THEN 0 ELSE 1 END")
    ->orderBy('vendor_reports.submitted_at', 'asc')
    ->get();
```

**Style:** URGENT workflow highlight với Tag severity="danger"

**Columns:**
- Code (với badge URGENT)
- Title
- Workflow (Tag)
- Step (Step key label)
- Submitted (date)
- Days pending
- Actions: **Approve** | **Reject** | View
  - Show badge "Cần chọn người" nếu `requires_selection = true`

#### Charts
1. **Phiếu tôi xử lý** (Doughnut: Approved vs Rejected)
2. **Response time trend** (Line chart - 6 tháng gần nhất)

#### Quick Actions
- Duyệt nhanh (multi-select với checkbox)
- Danh sách chờ duyệt
- Lịch sử của tôi

---

### 2.5 DEPT_HEAD (Trưởng phòng)

> User có `department.head_user_id = user.id`

#### Top Metrics
```php
$deptId = auth()->user()->department_id;

$metrics = [
    'pending_as_head' => VendorReportApprovalStep::where('assignee_user_id', $userId)
        ->where('step_key', 'DEPT_HEAD')
        ->where('status', 'PENDING')
        ->count(),
    'dept_reports' => VendorReport::whereHas('creator', fn($q) => $q->where('department_id', $deptId))
        ->count(),
    'approved_as_head' => VendorReportApprovalStep::where('acted_by', $userId)
        ->where('step_key', 'DEPT_HEAD')
        ->where('status', 'APPROVED')
        ->count(),
    'rejected_as_head' => VendorReportApprovalStep::where('acted_by', $userId)
        ->where('step_key', 'DEPT_HEAD')
        ->where('status', 'REJECTED')
        ->count(),
];
```

**Cards:**
1. **Chờ duyệt (TrP)**: Icon `pi pi-user-edit`
2. **Phiếu của phòng**: Icon `pi pi-building`
3. **Đã duyệt (TrP)**: Icon `pi pi-check-circle`
4. **Đã từ chối (TrP)**: Icon `pi pi-times-circle`

#### Pending Actions
**Data:** Steps với `step_key = 'DEPT_HEAD'` và `assignee_user_id = $userId`, `status = PENDING`

**Show badge "Cần chọn người"** cho workflow:
- SPECIAL_2 (chọn National Purchasing)
- SPECIAL_3 (chọn Tech Board)
- URGENT (chọn BOD)

#### Charts
1. **Phiếu phòng theo trạng thái** (Bar)
2. **Top creators trong phòng** (DataTable mini)

---

## 3. UI Components Implementation (PrimeVue v4 + Sakai)

### 3.1 Metric Card Component

**File:** `resources/js/Components/Dashboard/MetricCard.vue`

```html
<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    title: String,
    value: [Number, String],
    icon: String,
    trend: String, // "+5%" or "-3%"
    severity: { // PrimeVue severity
        type: String,
        default: 'info' // info, success, warn, danger, secondary, contrast
    },
    subtitle: String,
    onClick: Function,
});

const iconColor = computed(() => {
    const colors = {
        'info': 'text-blue-600 bg-blue-100',
        'success': 'text-green-600 bg-green-100',
        'warn': 'text-orange-600 bg-orange-100',
        'danger': 'text-red-600 bg-red-100',
        'secondary': 'text-purple-600 bg-purple-100',
        'contrast': 'text-gray-600 bg-gray-100',
    };
    return colors[props.severity] || colors.info;
});

const handleClick = () => {
    if (props.onClick) {
        props.onClick();
    }
};
</script>

<template>
    <div 
        class="card surface-card shadow-2 border-round p-4 cursor-pointer hover:shadow-4 transition-all transition-duration-300"
        :class="{ 'cursor-default': !onClick }"
        @click="handleClick"
    >
        <div class="flex align-items-center justify-content-between mb-3">
            <div>
                <span class="block text-500 font-medium mb-2">{{ title }}</span>
                <div class="text-900 font-bold text-3xl">{{ value }}</div>
                <span v-if="subtitle" class="text-500 text-sm">{{ subtitle }}</span>
            </div>
            <div 
                class="flex align-items-center justify-content-center border-circle"
                :class="iconColor"
                style="width: 3rem; height: 3rem;"
            >
                <i :class="icon" class="text-2xl"></i>
            </div>
        </div>
        <div v-if="trend" class="flex align-items-center">
            <i 
                :class="trend.startsWith('+') ? 'pi pi-arrow-up text-green-500' : 'pi pi-arrow-down text-red-500'"
                class="mr-2"
            ></i>
            <span class="text-sm" :class="trend.startsWith('+') ? 'text-green-500' : 'text-red-500'">
                {{ trend }}
            </span>
            <span class="text-500 text-sm ml-2">so với tháng trước</span>
        </div>
    </div>
</template>
```

**Usage in Dashboard.vue:**
```html
<div class="grid">
    <div class="col-12 md:col-6 lg:col-3" v-for="metric in metrics" :key="metric.id">
        <MetricCard
            :title="metric.title"
            :value="metric.value"
            :icon="metric.icon"
            :trend="metric.trend"
            :severity="metric.severity"
            :subtitle="metric.subtitle"
            :onClick="metric.onClick"
        />
    </div>
</div>
```

---

### 3.2 Pending Actions DataTable

**Style:** Giống Index.vue hiện có

```html
<div class="card">
    <h5 class="mb-4">
        <i class="pi pi-clock mr-2"></i>
        Phiếu cần xử lý
        <Badge v-if="pendingActions.length > 0" :value="pendingActions.length" severity="danger" class="ml-2" />
    </h5>
    
    <DataTable
        v-if="pendingActions.length > 0"
        :value="pendingActions"
        :paginator="true"
        :rows="10"
        stripedRows
        responsiveLayout="scroll"
        class="p-datatable-sm"
        :rowClass="(data) => data.workflow_type === 'URGENT' ? 'bg-red-50' : ''"
    >
        <Column field="code" header="Mã phiếu" sortable style="min-width: 12rem">
            <template #body="{ data }">
                <div class="flex align-items-center gap-2">
                    <Tag 
                        v-if="data.workflow_type === 'URGENT'" 
                        value="URGENT" 
                        severity="danger" 
                        class="text-xs"
                    />
                    <a 
                        :href="`/vendor-reports/${data.id}`"
                        class="text-primary font-semibold hover:underline"
                    >
                        {{ data.code }}
                    </a>
                </div>
            </template>
        </Column>

        <Column field="title" header="Tiêu đề" style="min-width: 20rem" />

        <Column field="workflow_type" header="Quy trình" style="min-width: 12rem">
            <template #body="{ data }">
                <Tag 
                    :value="data.workflow_type_label" 
                    :severity="getWorkflowSeverity(data.workflow_type)" 
                />
            </template>
        </Column>

        <Column field="current_step_label" header="Bước hiện tại" style="min-width: 12rem" />

        <Column field="assignee_name" header="Người duyệt" style="min-width: 12rem">
            <template #body="{ data }">
                {{ data.assignee_name || 'N/A' }}
            </template>
        </Column>

        <Column field="days_pending" header="Chờ (ngày)" sortable style="min-width: 8rem">
            <template #body="{ data }">
                <Badge 
                    :value="data.days_pending + 'd'" 
                    :severity="data.days_pending > 5 ? 'danger' : data.days_pending > 3 ? 'warn' : 'info'"
                />
            </template>
        </Column>

        <Column header="Actions" style="min-width: 12rem">
            <template #body="{ data }">
                <div class="flex gap-2">
                    <Button 
                        icon="pi pi-eye" 
                        text 
                        rounded 
                        severity="secondary"
                        v-tooltip.top="'Xem chi tiết'"
                        @click="router.visit(`/vendor-reports/${data.id}`)"
                    />
                    <Button 
                        v-if="canApprove(data)"
                        icon="pi pi-check" 
                        text 
                        rounded 
                        severity="success"
                        v-tooltip.top="'Phê duyệt'"
                        @click="approveReport(data)"
                    />
                    <Button 
                        v-if="canReject(data)"
                        icon="pi pi-times" 
                        text 
                        rounded 
                        severity="danger"
                        v-tooltip.top="'Từ chối'"
                        @click="rejectReport(data)"
                    />
                </div>
            </template>
        </Column>
    </DataTable>

    <!-- Empty State -->
    <div v-else class="text-center py-8">
        <i class="pi pi-check-circle text-6xl text-green-500 mb-3"></i>
        <p class="text-xl text-500">Không có phiếu cần xử lý</p>
        <p class="text-sm text-400">Tất cả phiếu đã được xử lý xong</p>
    </div>
</div>
```

**Helper Functions:**
```javascript
const getWorkflowSeverity = (workflowType) => {
    const severityMap = {
        'NORMAL': 'info',
        'SPECIAL_1': 'warn',
        'SPECIAL_2': 'secondary',
        'SPECIAL_3': 'contrast',
        'URGENT': 'danger',
    };
    return severityMap[workflowType] || 'info';
};
```

---

### 3.3 Charts Component (Using Chart.js)

**Install:**
```bash
npm install chart.js vue-chartjs
```

**File:** `resources/js/Components/Dashboard/DashboardChart.vue`

```html
<script setup>
import { ref, computed, onMounted } from 'vue';
import { Doughnut, Bar, Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    LineElement,
    PointElement
} from 'chart.js';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    LineElement,
    PointElement
);

const props = defineProps({
    type: {
        type: String,
        required: true,
        validator: (value) => ['doughnut', 'bar', 'line'].includes(value)
    },
    data: {
        type: Object,
        required: true
    },
    options: {
        type: Object,
        default: () => ({})
    }
});

const chartComponent = computed(() => {
    const components = {
        'doughnut': Doughnut,
        'bar': Bar,
        'line': Line
    };
    return components[props.type];
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                usePointStyle: true,
                padding: 15
            }
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: {
                size: 14
            },
            bodyFont: {
                size: 13
            }
        }
    },
    ...props.options
}));
</script>

<template>
    <component 
        :is="chartComponent" 
        :data="data" 
        :options="chartOptions"
    />
</template>
```

**Usage:**
```html
<!-- Status Chart (Doughnut) -->
<div class="card">
    <h5 class="mb-4">Phiếu theo trạng thái</h5>
    <DashboardChart
        type="doughnut"
        :data="statusChartData"
    />
</div>

<!-- Workflow Chart (Bar) -->
<div class="card">
    <h5 class="mb-4">Phiếu theo quy trình</h5>
    <DashboardChart
        type="bar"
        :data="workflowChartData"
        :options="{ indexAxis: 'x' }"
    />
</div>

<!-- Trend Chart (Line) -->
<div class="card">
    <h5 class="mb-4">Xu hướng 6 tháng gần nhất</h5>
    <DashboardChart
        type="line"
        :data="trendChartData"
        :options="{ 
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }"
    />
</div>
```

---

### 3.4 Quick Actions Grid

**Component:** Simple button grid with Sakai styling

```html
<div class="card">
    <h5 class="mb-4">
        <i class="pi pi-bolt mr-2"></i>
        Thao tác nhanh
    </h5>
    
    <div class="grid">
        <div 
            class="col-6 md:col-4 lg:col-6" 
            v-for="action in quickActions" 
            :key="action.id"
        >
            <Button
                :label="action.label"
                :icon="action.icon"
                outlined
                class="w-full justify-content-start"
                :severity="action.severity || 'secondary'"
                @click="router.visit(action.route)"
            />
        </div>
    </div>
</div>
```

**Data Structure:**
```javascript
const quickActions = ref([
    {
        id: 'create',
        label: 'Tạo phiếu mới',
        icon: 'pi pi-plus',
        route: '/vendor-reports/create',
        severity: 'success'
    },
    {
        id: 'my-drafts',
        label: 'Phiếu nháp',
        icon: 'pi pi-file-edit',
        route: '/vendor-reports?status=DRAFT',
        severity: 'secondary'
    },
    // ... more actions
]);
```

---

### 3.5 Recent Activities Timeline

**Component:** PrimeVue Timeline (style giống Show.vue)

```html
<div class="card">
    <h5 class="mb-4">
        <i class="pi pi-history mr-2"></i>
        Hoạt động gần đây
    </h5>
    
    <Timeline 
        :value="activities" 
        align="left"
        class="custom-timeline"
    >
        <template #marker="{ item }">
            <span 
                class="flex align-items-center justify-content-center text-white border-circle"
                :style="{ 
                    backgroundColor: getActivityColor(item.event),
                    width: '2.5rem',
                    height: '2.5rem'
                }"
            >
                <i :class="getActivityIcon(item.event)"></i>
            </span>
        </template>
        
        <template #content="{ item }">
            <div class="mb-3">
                <small class="text-500">
                    {{ formatTimeAgo(item.created_at) }}
                </small>
                <p class="mt-2 mb-0">
                    <strong>{{ item.causer_name }}</strong>
                    {{ item.description }}
                    <a 
                        :href="`/vendor-reports/${item.report_id}`"
                        class="text-primary hover:underline font-semibold ml-1"
                    >
                        {{ item.report_code }}
                    </a>
                </p>
            </div>
        </template>
    </Timeline>
</div>
```

**Helper Functions:**
```javascript
const getActivityIcon = (event) => {
    const iconMap = {
        'created': 'pi pi-file-plus',
        'submitted': 'pi pi-send',
        'approved': 'pi pi-check-circle',
        'rejected': 'pi pi-times-circle',
        'selected': 'pi pi-user-plus',
        'uploaded': 'pi pi-cloud-upload',
        'cloned': 'pi pi-copy',
        'canceled': 'pi pi-ban',
    };
    return iconMap[event] || 'pi pi-circle';
};

const getActivityColor = (event) => {
    const colorMap = {
        'created': '#3b82f6',     // blue
        'submitted': '#3b82f6',   // blue
        'approved': '#22c55e',    // green
        'rejected': '#ef4444',    // red
        'selected': '#f59e0b',    // orange
        'uploaded': '#8b5cf6',    // purple
        'cloned': '#06b6d4',      // cyan
        'canceled': '#6b7280',    // gray
    };
    return colorMap[event] || '#64748b';
};

const formatTimeAgo = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Vừa xong';
    if (diffMins < 60) return `${diffMins} phút trước`;
    
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours} giờ trước`;
    
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays} ngày trước`;
    
    return date.toLocaleDateString('vi-VN');
};
```

---

## 4. Backend Implementation

### 4.1 DashboardController

**File:** `app/Http/Controllers/DashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Display dashboard page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboardService->getMetrics($user),
            'pendingActions' => $this->dashboardService->getPendingActions($user),
            'quickActions' => $this->dashboardService->getQuickActions($user),
            'permissions' => [
                'can_create_report' => $user->can('create', \App\Models\VendorReport::class),
                'is_admin' => $user->hasRole('admin_system'),
                'is_dept_head' => $this->dashboardService->isDeptHead($user),
            ],
        ]);
    }

    /**
     * Get metrics data (AJAX)
     */
    public function metrics(Request $request)
    {
        return response()->json(
            $this->dashboardService->getMetrics($request->user())
        );
    }

    /**
     * Get chart data (AJAX)
     */
    public function chartData(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:status,workflow,trend,department',
            'period' => 'in:week,month,quarter,year',
        ]);

        return response()->json(
            $this->dashboardService->getChartData(
                $request->user(),
                $validated['type'],
                $validated['period'] ?? 'month'
            )
        );
    }

    /**
     * Get activities data (AJAX)
     */
    public function activities(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'integer|min:5|max:50',
            'event' => 'string|nullable',
        ]);

        return response()->json(
            $this->dashboardService->getActivities(
                $request->user(),
                $validated['limit'] ?? 15,
                $validated['event'] ?? null
            )
        );
    }
}
```

---

### 4.2 DashboardService

**File:** `app/Services/DashboardService.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    /**
     * Get metrics based on user role
     */
    public function getMetrics(User $user): array
    {
        if ($user->hasRole('admin_system')) {
            return $this->getAdminMetrics($user);
        }

        if ($user->hasRole('purchasing_admin')) {
            return $this->getPurchasingAdminMetrics($user);
        }

        // Check if user is dept head
        if ($this->isDeptHead($user)) {
            return array_merge(
                $this->getApproverMetrics($user),
                $this->getDeptHeadMetrics($user)
            );
        }

        // Check if user is approver
        $isApprover = VendorReportApprovalStep::where('assignee_user_id', $user->id)->exists();
        if ($isApprover) {
            return $this->getApproverMetrics($user);
        }

        // Default: requester metrics
        return $this->getRequesterMetrics($user);
    }

    private function getAdminMetrics(User $user): array
    {
        return [
            [
                'id' => 'total_reports',
                'title' => 'Tổng số phiếu',
                'value' => VendorReport::count(),
                'icon' => 'pi pi-file',
                'severity' => 'info',
                'trend' => $this->calculateTrend('reports'),
                'onClick' => fn() => '/vendor-reports',
            ],
            [
                'id' => 'active_users',
                'title' => 'Người dùng',
                'value' => User::where('is_active', true)->count(),
                'subtitle' => '/ ' . User::count() . ' tổng số',
                'icon' => 'pi pi-users',
                'severity' => 'success',
                'onClick' => fn() => '/admin/users',
            ],
            [
                'id' => 'active_departments',
                'title' => 'Phòng ban',
                'value' => Department::where('is_active', true)->count(),
                'icon' => 'pi pi-building',
                'severity' => 'secondary',
                'onClick' => fn() => '/admin/departments',
            ],
            [
                'id' => 'stuck_reports',
                'title' => 'Phiếu cần xử lý',
                'value' => VendorReport::where('status', 'IN_APPROVAL')
                    ->where('submitted_at', '<', now()->subDays(7))
                    ->count(),
                'subtitle' => '> 7 ngày chưa duyệt',
                'icon' => 'pi pi-exclamation-triangle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getRequesterMetrics(User $user): array
    {
        return [
            [
                'id' => 'my_total',
                'title' => 'Phiếu của tôi',
                'value' => VendorReport::where('created_by', $user->id)->count(),
                'icon' => 'pi pi-file-edit',
                'severity' => 'info',
            ],
            [
                'id' => 'my_in_approval',
                'title' => 'Đang chờ duyệt',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'IN_APPROVAL')
                    ->count(),
                'icon' => 'pi pi-clock',
                'severity' => 'warn',
            ],
            [
                'id' => 'my_approved',
                'title' => 'Đã duyệt',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'APPROVED')
                    ->count(),
                'icon' => 'pi pi-check-circle',
                'severity' => 'success',
            ],
            [
                'id' => 'my_rejected',
                'title' => 'Bị từ chối',
                'value' => VendorReport::where('created_by', $user->id)
                    ->where('status', 'REJECTED')
                    ->count(),
                'icon' => 'pi pi-times-circle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getPurchasingAdminMetrics(User $user): array
    {
        return [
            [
                'id' => 'all_reports',
                'title' => 'Tổng phiếu',
                'value' => VendorReport::count(),
                'icon' => 'pi pi-chart-bar',
                'severity' => 'info',
            ],
            [
                'id' => 'in_approval',
                'title' => 'Đang duyệt',
                'value' => VendorReport::where('status', 'IN_APPROVAL')->count(),
                'icon' => 'pi pi-hourglass',
                'severity' => 'warn',
            ],
            [
                'id' => 'supervised',
                'title' => 'Phiếu theo dõi',
                'value' => VendorReport::where('purchasing_admin_id', $user->id)->count(),
                'icon' => 'pi pi-eye',
                'severity' => 'secondary',
            ],
            [
                'id' => 'need_attention',
                'title' => 'Cần chú ý',
                'value' => VendorReport::where('status', 'IN_APPROVAL')
                    ->where('submitted_at', '<', now()->subDays(5))
                    ->count(),
                'subtitle' => '> 5 ngày',
                'icon' => 'pi pi-exclamation-triangle',
                'severity' => 'danger',
            ],
        ];
    }

    private function getApproverMetrics(User $user): array
    {
        $pendingCount = VendorReportApprovalStep::where('assignee_user_id', $user->id)
            ->where('status', 'PENDING')
            ->count();

        $approvedToday = VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'APPROVED')
            ->whereDate('acted_at', today())
            ->count();

        $totalRejected = VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'REJECTED')
            ->count();

        $avgResponseTime = $this->calculateAvgResponseTime($user);

        return [
            [
                'id' => 'pending_approval',
                'title' => 'Chờ tôi duyệt',
                'value' => $pendingCount,
                'icon' => 'pi pi-bell',
                'severity' => $pendingCount > 0 ? 'danger' : 'success',
            ],
            [
                'id' => 'approved_today',
                'title' => 'Duyệt hôm nay',
                'value' => $approvedToday,
                'icon' => 'pi pi-check',
                'severity' => 'success',
            ],
            [
                'id' => 'total_rejected',
                'title' => 'Đã từ chối',
                'value' => $totalRejected,
                'icon' => 'pi pi-times',
                'severity' => 'danger',
            ],
            [
                'id' => 'avg_response',
                'title' => 'Thời gian phản hồi TB',
                'value' => number_format($avgResponseTime, 1) . ' ngày',
                'icon' => 'pi pi-stopwatch',
                'severity' => $avgResponseTime < 2 ? 'success' : 'warn',
            ],
        ];
    }

    private function getDeptHeadMetrics(User $user): array
    {
        $deptId = $user->department_id;

        return [
            'dept_reports_total' => [
                'title' => 'Phiếu của phòng',
                'value' => VendorReport::whereHas('creator', fn($q) => 
                    $q->where('department_id', $deptId)
                )->count(),
                'icon' => 'pi pi-building',
            ],
        ];
    }

    /**
     * Get pending actions list
     */
    public function getPendingActions(User $user): array
    {
        if ($user->hasRole('admin_system')) {
            return $this->getStuckReports();
        }

        // For approvers: get pending approval steps
        $steps = VendorReportApprovalStep::with([
            'report:id,code,title,workflow_type,submitted_at',
            'report.creator:id,name',
            'report.department:id,name',
            'assignee:id,name'
        ])
            ->where('assignee_user_id', $user->id)
            ->where('status', 'PENDING')
            ->get();

        return $steps->map(function ($step) {
            $report = $step->report;
            return [
                'id' => $report->id,
                'code' => $report->code,
                'title' => $report->title,
                'workflow_type' => $report->workflow_type,
                'workflow_type_label' => $report->getWorkflowTypeLabel(),
                'current_step_label' => $step->getStepKeyLabel(),
                'assignee_name' => $step->assignee?->name,
                'days_pending' => now()->diffInDays($report->submitted_at),
                'submitted_at' => $report->submitted_at->format('d/m/Y'),
                'department_name' => $report->department?->name,
                'creator_name' => $report->creator?->name,
                'requires_selection' => $step->requires_selection,
            ];
        })
        ->sortByDesc(function ($item) {
            // URGENT lên đầu
            return $item['workflow_type'] === 'URGENT' ? 1000 + $item['days_pending'] : $item['days_pending'];
        })
        ->values()
        ->all();
    }

    private function getStuckReports(): array
    {
        return VendorReport::with(['creator', 'currentStep.assignee', 'department'])
            ->where('status', 'IN_APPROVAL')
            ->where('submitted_at', '<', now()->subDays(5))
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'code' => $report->code,
                    'title' => $report->title,
                    'workflow_type' => $report->workflow_type,
                    'workflow_type_label' => $report->getWorkflowTypeLabel(),
                    'current_step_label' => $report->currentStep?->getStepKeyLabel(),
                    'assignee_name' => $report->currentStep?->assignee?->name,
                    'days_pending' => now()->diffInDays($report->submitted_at),
                    'submitted_at' => $report->submitted_at->format('d/m/Y'),
                    'department_name' => $report->department?->name,
                ];
            })
            ->all();
    }

    /**
     * Get quick actions based on user permissions
     */
    public function getQuickActions(User $user): array
    {
        $actions = [];

        // Common: Create report
        if ($user->can('create', VendorReport::class)) {
            $actions[] = [
                'id' => 'create_report',
                'label' => 'Tạo phiếu mới',
                'icon' => 'pi pi-plus',
                'route' => '/vendor-reports/create',
                'severity' => 'success',
            ];
        }

        // Admin actions
        if ($user->hasRole('admin_system')) {
            $actions = array_merge($actions, [
                [
                    'id' => 'manage_users',
                    'label' => 'Quản lý users',
                    'icon' => 'pi pi-users',
                    'route' => '/admin/users',
                    'severity' => 'secondary',
                ],
                [
                    'id' => 'manage_departments',
                    'label' => 'Quản lý phòng ban',
                    'icon' => 'pi pi-building',
                    'route' => '/admin/departments',
                    'severity' => 'secondary',
                ],
                [
                    'id' => 'activity_logs',
                    'label' => 'Nhật ký hệ thống',
                    'icon' => 'pi pi-history',
                    'route' => '/activity-logs',
                    'severity' => 'secondary',
                ],
            ]);
        }

        // Purchasing admin actions
        if ($user->hasRole('purchasing_admin')) {
            $actions[] = [
                'id' => 'export',
                'label' => 'Export Excel',
                'icon' => 'pi pi-download',
                'route' => '/vendor-reports/export',
                'severity' => 'secondary',
            ];
        }

        // Requester actions
        $actions[] = [
            'id' => 'my_reports',
            'label' => 'Phiếu của tôi',
            'icon' => 'pi pi-file',
            'route' => '/vendor-reports?created_by=' . $user->id,
            'severity' => 'secondary',
        ];

        // Dept head actions
        if ($this->isDeptHead($user)) {
            $actions[] = [
                'id' => 'dept_reports',
                'label' => 'Phiếu của phòng',
                'icon' => 'pi pi-building',
                'route' => '/vendor-reports?department=' . $user->department_id,
                'severity' => 'secondary',
            ];
        }

        return $actions;
    }

    /**
     * Get chart data
     */
    public function getChartData(User $user, string $type, string $period = 'month'): array
    {
        return match($type) {
            'status' => $this->getStatusChartData($user),
            'workflow' => $this->getWorkflowChartData($user),
            'trend' => $this->getTrendChartData($user, $period),
            'department' => $this->getDepartmentChartData($user),
            default => [],
        };
    }

    private function getStatusChartData(User $user): array
    {
        $query = VendorReport::select('status', DB::raw('count(*) as count'))
            ->groupBy('status');

        // Filter by user role
        if (!$user->hasAnyRole(['admin_system', 'purchasing_admin'])) {
            $query->where('created_by', $user->id);
        }

        $data = $query->get();

        return [
            'labels' => $data->pluck('status')->map(fn($s) => ucfirst(strtolower($s)))->toArray(),
            'datasets' => [[
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => ['#94a3b8', '#3b82f6', '#22c55e', '#ef4444', '#6b7280'],
            ]],
        ];
    }

    private function getWorkflowChartData(User $user): array
    {
        $query = VendorReport::select('workflow_type', DB::raw('count(*) as count'))
            ->groupBy('workflow_type');

        if (!$user->hasAnyRole(['admin_system', 'purchasing_admin'])) {
            $query->where('created_by', $user->id);
        }

        $data = $query->get();

        return [
            'labels' => $data->pluck('workflow_type')->toArray(),
            'datasets' => [[
                'label' => 'Số lượng phiếu',
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => ['#3b82f6', '#f59e0b', '#8b5cf6', '#64748b', '#ef4444'],
            ]],
        ];
    }

    private function getTrendChartData(User $user, string $period): array
    {
        $months = 6;
        $labels = [];
        $approvedData = [];
        $rejectedData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M');

            $query = VendorReport::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);

            if (!$user->hasAnyRole(['admin_system', 'purchasing_admin'])) {
                $query->where('created_by', $user->id);
            }

            $approvedData[] = (clone $query)->where('status', 'APPROVED')->count();
            $rejectedData[] = (clone $query)->where('status', 'REJECTED')->count();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Đã duyệt',
                    'data' => $approvedData,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Từ chối',
                    'data' => $rejectedData,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    private function getDepartmentChartData(User $user): array
    {
        $data = VendorReport::select('departments.name', DB::raw('count(*) as count'))
            ->join('users', 'vendor_reports.created_by', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->pluck('name')->toArray(),
            'datasets' => [[
                'label' => 'Số phiếu',
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => '#3b82f6',
            ]],
        ];
    }

    /**
     * Get recent activities
     */
    public function getActivities(User $user, int $limit = 15, ?string $eventFilter = null): array
    {
        $query = Activity::with('causer:id,name', 'subject:id,code')
            ->where('log_name', 'vendor_report')
            ->orderByDesc('created_at')
            ->limit($limit);

        // Filter by user access
        if (!$user->hasRole('admin_system')) {
            $reportIds = $this->getUserRelatedReportIds($user);
            $query->whereIn('subject_id', $reportIds);
        }

        if ($eventFilter) {
            $query->where('event', $eventFilter);
        }

        return $query->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'causer_name' => $activity->causer?->name ?? 'System',
                'report_code' => $activity->subject?->code ?? 'N/A',
                'report_id' => $activity->subject_id,
                'created_at' => $activity->created_at->toISOString(),
            ];
        })->all();
    }

    /**
     * Check if user is department head
     */
    public function isDeptHead(User $user): bool
    {
        return $user->department 
            && $user->department->head_user_id === $user->id;
    }

    // Helper methods

    private function getUserRelatedReportIds(User $user): array
    {
        $createdIds = VendorReport::where('created_by', $user->id)->pluck('id');
        
        $approverIds = VendorReportApprovalStep::where('assignee_user_id', $user->id)
            ->pluck('report_id');
        
        return $createdIds->merge($approverIds)->unique()->all();
    }

    private function calculateAvgResponseTime(User $user): float
    {
        return VendorReportApprovalStep::where('acted_by', $user->id)
            ->whereNotNull('acted_at')
            ->get()
            ->map(fn($step) => $step->created_at->diffInHours($step->acted_at) / 24)
            ->avg() ?? 0;
    }

    private function calculateTrend(string $metric): string
    {
        // Simple trend calculation (can be enhanced)
        $currentMonth = VendorReport::whereMonth('created_at', now()->month)->count();
        $lastMonth = VendorReport::whereMonth('created_at', now()->subMonth()->month)->count();
        
        if ($lastMonth == 0) return '+0%';
        
        $percentage = (($currentMonth - $lastMonth) / $lastMonth) * 100;
        return sprintf('%+.1f%%', $percentage);
    }
}
```

### 4.1 Dashboard Metrics
```
GET /api/dashboard/metrics
```

**Response:**
```json
{
  "total_reports": 250,
  "pending_approvals": 15,
  "approved_today": 5,
  "my_drafts": 3,
  "avg_response_time": 1.5,
  "trends": {
    "total_reports": "+12%",
    "pending_approvals": "-5%"
  }
}
```

### 4.2 Pending Actions
```
GET /api/dashboard/pending-actions?role={role}
```

**Response:**
```json
{
  "data": [
    {
      "id": 123,
      "code": "2026/TM/045",
      "title": "Lựa chọn nhà cung cấp máy móc",
      "workflow": "URGENT",
      "current_step": "DEPT_HEAD",
      "assignee_name": "Nguyễn Văn A",
      "days_pending": 3,
      "submitted_at": "2026-01-30 10:00:00",
      "department_name": "Thương mại",
      "requires_selection": false
    }
  ],
  "total": 15
}
```

### 4.3 Chart Data
```
GET /api/dashboard/charts?type={type}&period={period}
```

**Parameters:**
- type: `status` | `workflow` | `trend` | `department` | `approver_performance`
- period: `week` | `month` | `quarter` | `year`

**Response:**
```json
{
  "labels": ["Draft", "In Approval", "Approved", "Rejected"],
  "datasets": [
    {
      "label": "Số lượng",
      "data": [10, 25, 150, 15],
      "colors": ["#94a3b8", "#3b82f6", "#22c55e", "#ef4444"]
    }
  ]
}
```

### 4.4 Recent Activities
```
GET /api/dashboard/activities?limit={limit}&filter={filter}
```

**Response:**
```json
{
  "data": [
    {
      "id": 456,
      "event": "approved",
      "description": "Đã phê duyệt phiếu",
      "causer_name": "Nguyễn Văn A",
      "report_code": "2026/TM/045",
      "report_id": 123,
      "created_at": "2026-02-03 14:30:00",
      "properties": {}
    }
  ],
  "total": 100
}
```

---

## 5. Controller & Service Implementation

### 5.1 DashboardController
```php
<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        $data = [
            'metrics' => $this->dashboardService->getMetrics($user),
            'pendingActions' => $this->dashboardService->getPendingActions($user),
            'quickActions' => $this->dashboardService->getQuickActions($user),
            'permissions' => [
                'canCreateReport' => $user->can('create', VendorReport::class),
                'isAdmin' => $user->hasRole('admin_system'),
                'isDeptHead' => $this->dashboardService->isDeptHead($user),
            ],
        ];

        return Inertia::render('Dashboard', $data);
    }

    public function metrics(Request $request)
    {
        return response()->json(
            $this->dashboardService->getMetrics($request->user())
        );
    }

    public function chartData(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:status,workflow,trend,department,approver_performance',
            'period' => 'in:week,month,quarter,year',
        ]);

        return response()->json(
            $this->dashboardService->getChartData(
                $request->user(),
                $validated['type'],
                $validated['period'] ?? 'month'
            )
        );
    }

    public function activities(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'integer|min:5|max:50',
            'filter' => 'string',
        ]);

        return response()->json(
            $this->dashboardService->getActivities(
                $request->user(),
                $validated['limit'] ?? 20,
                $validated['filter'] ?? null
            )
        );
    }
}
```

---

### 5.2 DashboardService
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\VendorReport;
use App\Models\VendorReportApprovalStep;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function getMetrics(User $user): array
    {
        $roles = $user->getRoleNames();
        
        return [
            'total_reports' => $this->getTotalReports($user, $roles),
            'pending_approvals' => $this->getPendingApprovals($user),
            'my_drafts' => $this->getMyDrafts($user),
            'approved_today' => $this->getApprovedToday($user),
            'rejected_total' => $this->getRejectedTotal($user),
            'avg_response_time' => $this->getAvgResponseTime($user),
            'system_health' => $this->getSystemHealth($user, $roles),
            'trends' => $this->getTrends($user, $roles),
        ];
    }

    public function getPendingActions(User $user): array
    {
        $roles = $user->getRoleNames();
        
        if ($user->hasRole('admin_system')) {
            // Admin: Phiếu stuck hoặc có vấn đề
            return $this->getStuckReports();
        }
        
        // Người duyệt: Phiếu chờ duyệt
        $pendingSteps = VendorReportApprovalStep::with([
            'report.creator',
            'report.department'
        ])
            ->where('assignee_user_id', $user->id)
            ->where('status', 'PENDING')
            ->get();
        
        return $pendingSteps->map(function ($step) {
            $report = $step->report;
            return [
                'id' => $report->id,
                'code' => $report->code,
                'title' => $report->title,
                'workflow' => $report->workflow_type,
                'current_step' => $step->step_key,
                'assignee_name' => $step->assignee?->name,
                'days_pending' => now()->diffInDays($report->submitted_at),
                'submitted_at' => $report->submitted_at,
                'department_name' => $report->department?->name,
                'creator_name' => $report->creator?->name,
                'requires_selection' => $step->requires_selection,
            ];
        })->sortByDesc(function ($item) {
            // URGENT lên đầu
            return $item['workflow'] === 'URGENT' ? 1000 + $item['days_pending'] : $item['days_pending'];
        })->values()->all();
    }

    public function getQuickActions(User $user): array
    {
        $actions = [];
        
        // Common actions
        if ($user->can('create', VendorReport::class)) {
            $actions[] = [
                'id' => 'create_report',
                'label' => 'Tạo phiếu mới',
                'icon' => 'pi pi-plus',
                'route' => '/vendor-reports/create',
                'color' => 'primary',
            ];
        }
        
        // Role-specific actions
        if ($user->hasRole('admin_system')) {
            $actions = array_merge($actions, [
                ['id' => 'manage_users', 'label' => 'Quản lý users', 'icon' => 'pi pi-users', 'route' => '/admin/users'],
                ['id' => 'manage_departments', 'label' => 'Quản lý phòng ban', 'icon' => 'pi pi-building', 'route' => '/admin/departments'],
                ['id' => 'reports', 'label' => 'Báo cáo chi tiết', 'icon' => 'pi pi-chart-bar', 'route' => '/reports'],
                ['id' => 'settings', 'label' => 'Cài đặt', 'icon' => 'pi pi-cog', 'route' => '/settings'],
            ]);
        }
        
        if ($user->hasRole('purchasing_admin')) {
            $actions[] = ['id' => 'analytics', 'label' => 'Phân tích workflow', 'icon' => 'pi pi-chart-line', 'route' => '/analytics'];
            $actions[] = ['id' => 'export', 'label' => 'Export dữ liệu', 'icon' => 'pi pi-download', 'route' => '/export'];
        }
        
        // Dept head actions
        if ($this->isDeptHead($user)) {
            $actions[] = ['id' => 'dept_reports', 'label' => 'Phiếu của phòng', 'icon' => 'pi pi-building', 'route' => '/vendor-reports?department=' . $user->department_id];
        }
        
        return $actions;
    }

    public function getChartData(User $user, string $type, string $period): array
    {
        return match($type) {
            'status' => $this->getStatusChartData($user, $period),
            'workflow' => $this->getWorkflowChartData($user, $period),
            'trend' => $this->getTrendChartData($user, $period),
            'department' => $this->getDepartmentChartData($user, $period),
            'approver_performance' => $this->getApproverPerformanceData($user, $period),
            default => [],
        };
    }

    public function getActivities(User $user, int $limit, ?string $filter): array
    {
        $query = Activity::with('causer', 'subject')
            ->where('log_name', 'vendor_report')
            ->orderByDesc('created_at')
            ->limit($limit);
        
        // Filter based on user role
        if (!$user->hasRole('admin_system')) {
            // Non-admin: Only see activities related to their reports
            $reportIds = $this->getUserRelatedReportIds($user);
            $query->whereIn('subject_id', $reportIds);
        }
        
        if ($filter) {
            $query->where('event', $filter);
        }
        
        return $query->get()->map(function ($activity) {
            return [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'causer_name' => $activity->causer?->name ?? 'System',
                'report_code' => $activity->subject?->code ?? 'N/A',
                'report_id' => $activity->subject_id,
                'created_at' => $activity->created_at,
                'properties' => $activity->properties,
            ];
        })->all();
    }

    public function isDeptHead(User $user): bool
    {
        return $user->department 
            && $user->department->head_user_id === $user->id;
    }

    // Private helper methods...
    private function getTotalReports(User $user, $roles): int
    {
        if ($user->hasRole('admin_system') || $user->hasRole('purchasing_admin')) {
            return VendorReport::count();
        }
        
        return VendorReport::where('created_by', $user->id)->count();
    }

    private function getPendingApprovals(User $user): int
    {
        return VendorReportApprovalStep::where('assignee_user_id', $user->id)
            ->where('status', 'PENDING')
            ->count();
    }

    private function getMyDrafts(User $user): int
    {
        return VendorReport::where('created_by', $user->id)
            ->where('status', 'DRAFT')
            ->count();
    }

    private function getApprovedToday(User $user): int
    {
        return VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'APPROVED')
            ->whereDate('acted_at', today())
            ->count();
    }

    private function getRejectedTotal(User $user): int
    {
        return VendorReportApprovalStep::where('acted_by', $user->id)
            ->where('status', 'REJECTED')
            ->count();
    }

    private function getAvgResponseTime(User $user): float
    {
        return VendorReportApprovalStep::where('acted_by', $user->id)
            ->whereNotNull('acted_at')
            ->get()
            ->map(function ($step) {
                return $step->acted_at->diffInHours($step->created_at) / 24; // Convert to days
            })
            ->avg() ?? 0;
    }

    private function getStuckReports(): array
    {
        return VendorReport::with(['currentStep.assignee', 'department'])
            ->where('status', 'IN_APPROVAL')
            ->where('submitted_at', '<', now()->subDays(5))
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'code' => $report->code,
                    'title' => $report->title,
                    'workflow' => $report->workflow_type,
                    'current_step' => $report->currentStep?->step_key,
                    'assignee_name' => $report->currentStep?->assignee?->name,
                    'days_pending' => now()->diffInDays($report->submitted_at),
                    'submitted_at' => $report->submitted_at,
                    'department_name' => $report->department?->name,
                ];
            })
            ->all();
    }

    private function getStatusChartData(User $user, string $period): array
    {
        $query = VendorReport::select('status', DB::raw('count(*) as count'))
            ->groupBy('status');
        
        if (!$user->hasRole('admin_system')) {
            $query->where('created_by', $user->id);
        }
        
        $data = $query->get();
        
        return [
            'labels' => $data->pluck('status')->toArray(),
            'datasets' => [[
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => [
                    '#94a3b8', // DRAFT
                    '#3b82f6', // SUBMITTED
                    '#f59e0b', // IN_APPROVAL
                    '#22c55e', // APPROVED
                    '#ef4444', // REJECTED
                    '#6b7280', // CANCELED
                ],
            ]],
        ];
    }

    private function getWorkflowChartData(User $user, string $period): array
    {
        $query = VendorReport::select('workflow_type', DB::raw('count(*) as count'))
            ->groupBy('workflow_type');
        
        if (!$user->hasRole('admin_system')) {
            $query->where('created_by', $user->id);
        }
        
        $data = $query->get();
        
        return [
            'labels' => $data->pluck('workflow_type')->toArray(),
            'datasets' => [[
                'label' => 'Số lượng phiếu',
                'data' => $data->pluck('count')->toArray(),
                'backgroundColor' => ['#3b82f6', '#f59e0b', '#8b5cf6', '#64748b', '#ef4444'],
            ]],
        ];
    }

    private function getTrendChartData(User $user, string $period): array
    {
        // Implementation for trend over time
        // Group by month, show APPROVED vs REJECTED
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'label' => 'Approved',
                    'data' => [12, 19, 15, 25, 22, 30],
                    'borderColor' => '#22c55e',
                    'fill' => false,
                ],
                [
                    'label' => 'Rejected',
                    'data' => [2, 3, 2, 5, 3, 4],
                    'borderColor' => '#ef4444',
                    'fill' => false,
                ],
            ],
        ];
    }

    private function getDepartmentChartData(User $user, string $period): array
    {
        // Top 10 departments by report count
        return [
            'labels' => ['TM', 'KSNB', 'BT', 'BGĐ', 'HC'],
            'datasets' => [[
                'label' => 'Số phiếu',
                'data' => [45, 38, 32, 28, 25],
                'backgroundColor' => '#3b82f6',
            ]],
        ];
    }

    private function getApproverPerformanceData(User $user, string $period): array
    {
        // Table data: Top approvers with avg response time
        return [
            'headers' => ['Name', 'Role', 'Avg Response (days)', 'Total Approved'],
            'rows' => [
                ['Nguyễn Văn A', 'Internal Control', 1.5, 45],
                ['Trần Thị B', 'BOD', 2.0, 38],
                ['Lê Văn C', 'Dept Head', 1.2, 52],
            ],
        ];
    }

    private function getUserRelatedReportIds(User $user): array
    {
        // Reports created by user OR user is approver
        $createdIds = VendorReport::where('created_by', $user->id)->pluck('id');
        
        $approverIds = VendorReportApprovalStep::where('assignee_user_id', $user->id)
            ->pluck('report_id');
        
        return $createdIds->merge($approverIds)->unique()->all();
    }

    private function getSystemHealth(User $user, $roles): ?array
    {
        if (!$user->hasRole('admin_system')) {
            return null;
        }
        
        return [
            'queue_jobs' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'active_users' => User::where('is_active', true)->count(),
        ];
    }

    private function getTrends(User $user, $roles): array
    {
        // Calculate % change compared to last period
        // Simplified for now
        return [
            'total_reports' => '+12%',
            'pending_approvals' => '-5%',
            'avg_response_time' => '-0.3 days',
        ];
    }
}
```

---

## 6. Frontend Component (Vue 3 + PrimeVue)

### 6.1 Dashboard.vue (Main Page)
```html
<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Badge from 'primevue/badge';
import Button from 'primevue/button';
import Timeline from 'primevue/timeline';
import Chart from 'primevue/chart';

import MetricCard from '@/Components/Dashboard/MetricCard.vue';
import QuickActionCard from '@/Components/Dashboard/QuickActionCard.vue';

const props = defineProps({
    metrics: Object,
    pendingActions: Array,
    quickActions: Array,
    permissions: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const chartData = ref({});
const activities = ref([]);
const loading = ref(false);

onMounted(async () => {
    await loadChartData();
    await loadActivities();
});

const loadChartData = async () => {
    try {
        const [statusRes, workflowRes, trendRes] = await Promise.all([
            axios.get('/api/dashboard/charts?type=status'),
            axios.get('/api/dashboard/charts?type=workflow'),
            axios.get('/api/dashboard/charts?type=trend'),
        ]);
        
        chartData.value = {
            status: statusRes.data,
            workflow: workflowRes.data,
            trend: trendRes.data,
        };
    } catch (error) {
        console.error('Failed to load chart data:', error);
    }
};

const loadActivities = async () => {
    try {
        const res = await axios.get('/api/dashboard/activities?limit=15');
        activities.value = res.data.data;
    } catch (error) {
        console.error('Failed to load activities:', error);
    }
};

const getWorkflowSeverity = (workflow) => {
    const severityMap = {
        'NORMAL': 'info',
        'SPECIAL_1': 'warn',
        'SPECIAL_2': 'secondary',
        'SPECIAL_3': 'contrast',
        'URGENT': 'danger',
    };
    return severityMap[workflow] || 'info';
};

const viewReport = (id) => {
    window.location.href = `/vendor-reports/${id}`;
};

const getActivityIcon = (event) => {
    const iconMap = {
        'created': 'pi-file-plus',
        'submitted': 'pi-send',
        'approved': 'pi-check-circle',
        'rejected': 'pi-times-circle',
        'selected': 'pi-user-plus',
        'uploaded': 'pi-cloud-upload',
        'cloned': 'pi-copy',
        'canceled': 'pi-ban',
    };
    return `pi ${iconMap[event] || 'pi-circle'}`;
};

const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Vừa xong';
    if (diffMins < 60) return `${diffMins} phút trước`;
    
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours} giờ trước`;
    
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays} ngày trước`;
    
    return date.toLocaleDateString('vi-VN');
};
</script>

<template>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    Xin chào, {{ user.name }} 👋
                </h1>
                <p class="text-gray-600 mt-1">
                    {{ user.department?.name }} - {{ user.roles?.map(r => r.name).join(', ') }}
                </p>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="metrics-grid">
            <MetricCard
                v-for="(metric, key) in metrics"
                :key="key"
                :title="metric.label"
                :value="metric.value"
                :icon="metric.icon"
                :trend="metric.trend"
                :color="metric.color"
                @click="metric.onClick"
            />
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column: Pending Actions & Charts -->
            <div class="left-column">
                <!-- Pending Actions Table -->
                <Card class="mb-4">
                    <template #title>
                        <div class="flex items-center justify-between">
                            <span>
                                <i class="pi pi-clock mr-2"></i>
                                Phiếu cần xử lý
                            </span>
                            <Badge :value="pendingActions.length" severity="danger" />
                        </div>
                    </template>
                    <template #content>
                        <DataTable
                            v-if="pendingActions.length > 0"
                            :value="pendingActions"
                            :paginator="true"
                            :rows="5"
                            responsiveLayout="scroll"
                            stripedRows
                        >
                            <Column field="code" header="Mã phiếu" style="min-width: 150px">
                                <template #body="{ data }">
                                    <div>
                                        <Tag
                                            v-if="data.workflow === 'URGENT'"
                                            severity="danger"
                                            value="URGENT"
                                            class="mr-2"
                                        />
                                        <a
                                            :href="`/vendor-reports/${data.id}`"
                                            class="text-primary-600 hover:underline font-semibold"
                                        >
                                            {{ data.code }}
                                        </a>
                                    </div>
                                </template>
                            </Column>
                            <Column field="title" header="Tiêu đề" style="min-width: 200px" />
                            <Column field="workflow" header="Loại" style="min-width: 120px">
                                <template #body="{ data }">
                                    <Tag
                                        :severity="getWorkflowSeverity(data.workflow)"
                                        :value="data.workflow"
                                    />
                                </template>
                            </Column>
                            <Column field="days_pending" header="Chờ" style="min-width: 80px">
                                <template #body="{ data }">
                                    <Badge
                                        :value="`${data.days_pending}d`"
                                        :severity="data.days_pending > 5 ? 'danger' : data.days_pending > 3 ? 'warn' : 'info'"
                                    />
                                </template>
                            </Column>
                            <Column header="Actions" style="min-width: 150px">
                                <template #body="{ data }">
                                    <div class="flex gap-2">
                                        <Button
                                            icon="pi pi-eye"
                                            text
                                            rounded
                                            @click="viewReport(data.id)"
                                            v-tooltip.top="'Xem chi tiết'"
                                        />
                                        <Button
                                            v-if="permissions.canApprove"
                                            icon="pi pi-check"
                                            text
                                            rounded
                                            severity="success"
                                            v-tooltip.top="'Phê duyệt'"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                        <div v-else class="text-center py-8 text-gray-500">
                            <i class="pi pi-check-circle text-4xl mb-3"></i>
                            <p>Không có phiếu cần xử lý</p>
                        </div>
                    </template>
                </Card>

                <!-- Charts -->
                <div class="charts-grid">
                    <!-- Status Chart -->
                    <Card>
                        <template #title>Phiếu theo trạng thái</template>
                        <template #content>
                            <Chart
                                v-if="chartData.status"
                                type="doughnut"
                                :data="chartData.status"
                                :options="{ responsive: true, maintainAspectRatio: true }"
                                class="chart-container"
                            />
                        </template>
                    </Card>

                    <!-- Workflow Chart -->
                    <Card>
                        <template #title>Phiếu theo loại workflow</template>
                        <template #content>
                            <Chart
                                v-if="chartData.workflow"
                                type="bar"
                                :data="chartData.workflow"
                                :options="{ responsive: true, maintainAspectRatio: true }"
                                class="chart-container"
                            />
                        </template>
                    </Card>

                    <!-- Trend Chart -->
                    <Card class="col-span-2">
                        <template #title>Xu hướng phiếu theo thời gian</template>
                        <template #content>
                            <Chart
                                v-if="chartData.trend"
                                type="line"
                                :data="chartData.trend"
                                :options="{
                                    responsive: true,
                                    maintainAspectRatio: true,
                                    plugins: {
                                        legend: { position: 'top' }
                                    }
                                }"
                                class="chart-container"
                            />
                        </template>
                    </Card>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Activities -->
            <div class="right-column">
                <!-- Quick Actions -->
                <Card class="mb-4">
                    <template #title>
                        <i class="pi pi-bolt mr-2"></i>
                        Thao tác nhanh
                    </template>
                    <template #content>
                        <div class="quick-actions-grid">
                            <QuickActionCard
                                v-for="action in quickActions"
                                :key="action.id"
                                :label="action.label"
                                :icon="action.icon"
                                :route="action.route"
                                :color="action.color"
                            />
                        </div>
                    </template>
                </Card>

                <!-- Recent Activities -->
                <Card>
                    <template #title>
                        <i class="pi pi-history mr-2"></i>
                        Hoạt động gần đây
                    </template>
                    <template #content>
                        <Timeline :value="activities" align="left" class="activities-timeline">
                            <template #marker="{ item }">
                                <span class="activity-marker" :class="`activity-${item.event}`">
                                    <i :class="getActivityIcon(item.event)"></i>
                                </span>
                            </template>
                            <template #content="{ item }">
                                <div class="activity-content">
                                    <small class="activity-time text-gray-500">
                                        {{ formatTime(item.created_at) }}
                                    </small>
                                    <p class="mt-1">
                                        <strong>{{ item.causer_name }}</strong>
                                        {{ item.description }}
                                        <a
                                            :href="`/vendor-reports/${item.report_id}`"
                                            class="text-primary-600 hover:underline"
                                        >
                                            {{ item.report_code }}
                                        </a>
                                    </p>
                                </div>
                            </template>
                        </Timeline>
                    </template>
                </Card>
            </div>
        </div>
    </div>
</template>

<style scoped>
.dashboard-container {
    padding: 2rem;
    max-width: 1920px;
    margin: 0 auto;
}

.dashboard-header {
    margin-bottom: 2rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 1.5rem;
}

@media (max-width: 1280px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

@media (max-width: 768px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .charts-grid .col-span-2 {
        grid-column: span 1;
    }
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.chart-container {
    height: 300px;
}

.activities-timeline {
    max-height: 600px;
    overflow-y: auto;
}

.activity-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
}

.activity-created { background: #3b82f6; }
.activity-submitted { background: #3b82f6; }
.activity-approved { background: #22c55e; }
.activity-rejected { background: #ef4444; }
.activity-selected { background: #f59e0b; }
.activity-uploaded { background: #8b5cf6; }
.activity-cloned { background: #06b6d4; }
.activity-canceled { background: #6b7280; }

.activity-content {
    padding: 0.5rem 0;
}

.activity-time {
    font-size: 0.875rem;
}
</style>
```

---

## 7. Route Configuration

**routes/web.php:**
```php
// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Dashboard API endpoints
Route::prefix('api/dashboard')->middleware(['auth'])->group(function () {
    Route::get('/metrics', [DashboardController::class, 'metrics']);
    Route::get('/charts', [DashboardController::class, 'chartData']);
    Route::get('/activities', [DashboardController::class, 'activities']);
});
```

---

## 8. Summary & Next Steps

### 8.1 Đã thiết kế
✅ Layout dashboard responsive cho desktop & mobile  
✅ Metrics cards theo từng role  
✅ Pending actions table với priority sorting  
✅ Charts & Analytics (Status, Workflow, Trend, Department, Approver Performance)  
✅ Recent activities timeline với filter  
✅ Quick actions grid theo permissions  
✅ API endpoints cho real-time data  
✅ DashboardService với logic phân quyền  
✅ Vue component với PrimeVue  

### 8.2 Tính năng nổi bật
- **Role-based content**: Mỗi user chỉ thấy thông tin phù hợp với quyền hạn
- **Action-oriented**: Ưu tiên hiển thị phiếu cần xử lý ngay
- **Real-time metrics**: Thống kê cập nhật theo thời gian thực
- **Professional charts**: Biểu đồ đẹp, trực quan với Chart.js/ApexCharts
- **Responsive design**: Hoạt động tốt trên mọi thiết bị
- **Performance optimized**: Load data song song, lazy load charts

### 8.3 Implementation Checklist

**Backend:**
- [ ] Tạo `app/Http/Controllers/DashboardController.php`
- [ ] Tạo `app/Services/DashboardService.php`
- [ ] Thêm routes vào `routes/web.php`
- [ ] Test API endpoints với Postman/Insomnia

**Frontend:**
- [ ] Cài đặt Chart.js: `npm install chart.js vue-chartjs`
- [ ] Tạo `resources/js/Pages/Dashboard.vue`
- [ ] Tạo `resources/js/Components/Dashboard/MetricCard.vue`
- [ ] Tạo `resources/js/Components/Dashboard/DashboardChart.vue`
- [ ] Test responsive trên mobile/tablet/desktop

**Testing:**
- [ ] Test metrics calculation cho từng role
- [ ] Test pending actions filter (URGENT lên đầu)
- [ ] Test permissions & authorization (role-based content)
- [ ] Test chart data rendering
- [ ] Test activities timeline
- [ ] Test quick actions routing
- [ ] Performance testing (load time < 2s)

**Integration:**
- [ ] Cập nhật navigation menu để link đến `/dashboard`
- [ ] Set dashboard làm trang mặc định sau login
- [ ] Test toast notifications
- [ ] Test với real data từ database

---

## 9. Ghi chú kỹ thuật quan trọng

### 9.1 Tích hợp với source code hiện có

**Models & Relationships:**
- Sử dụng `VendorReport::with()` để eager load relationships (tránh N+1)
- Dùng `VendorReportResource` để format data
- Query approvalSteps với proper indexing

**Services:**
- DashboardService tách biệt logic, dễ test
- Reuse existing: VendorReportSubmissionService, VendorReportApprovalService
- Cache metrics nếu cần (Redis): `Cache::remember('dashboard:metrics:' . $userId, 300, ...)`

**Authorization:**
- Dùng Policy: `$user->can('view', $report)`
- Check role: `$user->hasRole('admin_system')`
- Check dept head: `$user->department->head_user_id === $user->id`

### 9.2 Performance Optimization

**Backend:**
```php
// Cache metrics for 5 minutes
public function getMetrics(User $user): array
{
    return Cache::remember("dashboard:metrics:{$user->id}", 300, function() use ($user) {
        // ... expensive queries
    });
}

// Use query optimization
VendorReport::query()
    ->select(['id', 'code', 'title', 'status', 'workflow_type', 'submitted_at'])
    ->with(['creator:id,name', 'department:id,name'])
    ->where('status', 'IN_APPROVAL')
    ->limit(10)
    ->get();
```

**Frontend:**
```javascript
// Lazy load charts
const chartData = ref(null);

onMounted(async () => {
    await nextTick(); // Wait for DOM render
    chartData.value = await loadChartData();
});

// Debounce refresh
const refreshDashboard = debounce(async () => {
    await loadMetrics();
}, 1000);
```

### 9.3 Responsive Breakpoints (Sakai Vue)

```scss
// Mobile
@media (max-width: 576px) {
    .grid { gap: 0.5rem; }
    .col-12 { width: 100%; }
}

// Tablet
@media (min-width: 768px) {
    .col-md-6 { width: 50%; }
}

// Desktop
@media (min-width: 992px) {
    .col-lg-3 { width: 25%; }
    .col-lg-4 { width: 33.333%; }
    .col-lg-8 { width: 66.666%; }
}
```

### 9.4 Sample Data for Testing

```php
// Seeder: database/seeders/DashboardTestSeeder.php
public function run()
{
    // Create test reports with different statuses
    VendorReport::factory()->count(50)->create([
        'status' => 'APPROVED',
        'workflow_type' => 'NORMAL',
    ]);
    
    VendorReport::factory()->count(20)->create([
        'status' => 'IN_APPROVAL',
        'workflow_type' => 'URGENT',
        'submitted_at' => now()->subDays(rand(1, 10)),
    ]);
    
    // Create approval steps
    // ... etc
}
```

**Chạy seeder:**
```bash
php artisan db:seed --class=DashboardTestSeeder
```

---

## 10. Screenshots & Wireframes

### 10.1 Dashboard Layout (Desktop)

```
┌────────────────────────────────────────────────────────────────┐
│ Sidebar │  Xin chào, Nguyễn Văn A                              │
│         │  Phòng Thương mại - requester                          │
│ • Home  │                                                        │
│ • Dash  │  [Card: 45]  [Card: 12]  [Card: 30]  [Card: 3]       │
│ • VR    │  Phiếu tôi   Chờ duyệt   Đã duyệt   Từ chối          │
│ • Admin │                                                        │
│         │  ┌──────────────────────────────┐ ┌──────────────┐   │
│         │  │ Phiếu cần xử lý              │ │ Thao tác     │   │
│         │  │ ┌──────────────────────────┐ │ │ nhanh        │   │
│         │  │ │ URGENT 2026/TM/045 ...   │ │ │ • Tạo phiếu  │   │
│         │  │ │ 2026/BT/046 ...          │ │ │ • Phiếu nháp │   │
│         │  │ └──────────────────────────┘ │ │ • Phiếu tôi  │   │
│         │  └──────────────────────────────┘ └──────────────┘   │
│         │                                                        │
│         │  ┌──────────┐ ┌──────────┐       ┌──────────────┐   │
│         │  │ Doughnut │ │ Bar Chart│       │ Activities   │   │
│         │  │ Status   │ │ Workflow │       │ Timeline     │   │
│         │  └──────────┘ └──────────┘       └──────────────┘   │
│         │                                                        │
│         │  ┌─────────────────────────────────────┐             │
│         │  │ Line Chart: Trend 6 tháng           │             │
│         │  └─────────────────────────────────────┘             │
└────────────────────────────────────────────────────────────────┘
```

### 10.2 Mobile Layout

```
┌─────────────────────┐
│ ☰  Dashboard        │
├─────────────────────┤
│ Xin chào, Nguyễn    │
│ Văn A               │
│                     │
│ [Card: 45 Phiếu]    │
│ [Card: 12 Chờ]      │
│ [Card: 30 Duyệt]    │
│ [Card: 3 Từ chối]   │
│                     │
│ Phiếu cần xử lý     │
│ ┌─────────────────┐ │
│ │ 2026/TM/045     │ │
│ │ Title...        │ │
│ └─────────────────┘ │
│                     │
│ Biểu đồ             │
│ [Doughnut Chart]    │
│                     │
│ Thao tác nhanh      │
│ [+ Tạo] [📋 Nháp]  │
│                     │
│ Hoạt động           │
│ • User A approved.. │
│ • User B created... │
└─────────────────────┘
```

---

**END OF DASHBOARD DESIGN DOCUMENT**

> **Lưu ý**: Tài liệu này đã được thiết kế cụ thể cho source code hiện có với:
> - Sakai Vue Template layout
> - PrimeVue v4 components (Card, DataTable, Timeline, Chart, Tag, Badge, Button)
> - Inertia.js routing
> - Laravel Services pattern
> - Role-based authorization với Spatie Laravel Permission
> 
> **Để triển khai**: Follow implementation checklist từng bước, test kỹ permissions và responsive design.
