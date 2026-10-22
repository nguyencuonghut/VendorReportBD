<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorReport;
use App\Notifications\VendorReportApprovalRequired;
use App\Notifications\VendorReportApproved;
use App\Notifications\VendorReportSubmitted;
use App\Services\VendorReportApprovalService;
use App\Services\VendorReportSubmissionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VendorReportInternalControlBodWorkflowTest extends TestCase
{
    private Department $department;

    private User $requester;

    private User $internalControl;

    private User $director;

    private User $otherDirector;

    private int $draftNumber = 0;

    private function migrateIsolatedDatabase(): void
    {
        // Fail before migrate:fresh if a cached/local config points at application data.
        $this->assertTrue($this->app->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        if (config('database.connections.sqlite.url')) {
            throw new \RuntimeException('Workflow tests must not use a database URL override.');
        }

        // The in-memory connection is discarded with the application; no schema rollback is needed.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateIsolatedDatabase();

        Notification::fake();
        Storage::fake('private');
        $this->withoutVite();

        foreach (['requester', 'internal_control', 'bod', 'dept_head', 'purchasing_admin', 'accountant', 'national_purchasing', 'tech_board'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->department = Department::create([
            'code' => 'TEST',
            'name' => 'Workflow test department',
            'head_user_id' => null,
            'is_active' => true,
        ]);
        $this->requester = $this->userWithRole('requester');
        $this->internalControl = $this->userWithRole('internal_control');
        $this->director = $this->userWithRole('bod');
        $this->otherDirector = $this->userWithRole('bod');

        // Use the asset version provided by the first page, as the Inertia client does.
        $initialPage = $this->actingAs($this->requester)->get(route('vendor-reports.create'))->assertOk();
        $this->withHeader('X-Inertia-Version', (string) $initialPage->viewData('page')['version']);
    }

    public function test_ac01_employee_can_create_a_draft_with_the_new_workflow(): void
    {
        $this->actingAs($this->requester)
            ->post(route('vendor-reports.store'), $this->draftPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vendor-reports.index'));

        $this->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.reports.data.0.workflow_type', 'INTERNAL_CONTROL_BOD')
            ->assertJsonPath('props.reports.data.0.status', 'DRAFT')
            ->assertJsonPath('props.reports.data.0.workflow_type_label', 'KSNB → Giám đốc (bỏ qua Trưởng phòng)');
    }

    public function test_ac01_employee_can_change_an_existing_draft_to_the_new_workflow(): void
    {
        $id = $this->createDraft('NORMAL');

        $this->actingAs($this->requester)
            ->put(route('vendor-reports.update', $id), ['title' => 'Đề nghị đã sửa', 'workflow_type' => 'INTERNAL_CONTROL_BOD'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vendor-reports.show', $id));

        $this->showReport($id)
            ->assertJsonPath('props.report.data.workflow_type', 'INTERNAL_CONTROL_BOD')
            ->assertJsonPath('props.report.data.status', 'DRAFT');
    }

    public function test_ac01_unknown_workflow_is_rejected_on_create_and_update(): void
    {
        $this->actingAs($this->requester)
            ->post(route('vendor-reports.store'), $this->draftPayload('UNKNOWN'))
            ->assertSessionHasErrors('workflow_type');

        $this->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(0, 'props.reports.data');

        $id = $this->createDraft('NORMAL');
        $this->put(route('vendor-reports.update', $id), ['title' => 'Đề nghị đã sửa', 'workflow_type' => 'UNKNOWN'])
            ->assertSessionHasErrors('workflow_type');

        $this->showReport($id)->assertJsonPath('props.report.data.workflow_type', 'NORMAL');
    }

    public function test_ac01_create_edit_and_filter_options_expose_a_label_for_every_workflow(): void
    {
        $id = $this->createDraft('NORMAL');

        foreach (['vendor-reports.create', 'vendor-reports.edit', 'vendor-reports.index'] as $name) {
            $response = $this->actingAs($this->requester)
                ->get(route($name, $name === 'vendor-reports.edit' ? $id : []), ['X-Inertia' => 'true'])
                ->assertOk();
            $options = $response->json('props.workflows');
            $this->assertCount(count(config('vendor_report_workflows')), $options);
            $this->assertSame('KSNB → Giám đốc (bỏ qua Trưởng phòng)', $options['INTERNAL_CONTROL_BOD']);

            foreach (array_keys(config('vendor_report_workflows')) as $workflow) {
                $this->assertArrayHasKey($workflow, $options);
                $this->assertNotEmpty($options[$workflow]);
                $this->assertNotSame($workflow, $options[$workflow]);
            }
        }
    }

    public function test_ac06_ac08_two_manual_approvals_complete_the_configured_workflow(): void
    {
        // A head-equipped department isolates the new config from the Step 3 builder gap.
        $id = $this->submitDraftWithDepartmentHead();
        $this->assertAtInternalControl($id);
        Notification::assertSentTo($this->internalControl, VendorReportSubmitted::class);
        Notification::assertNotSentTo($this->director, VendorReportSubmitted::class);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'IN_APPROVAL')
            ->assertJsonPath('props.currentStep.data.step_key', 'BOD')
            ->assertJsonPath('props.approvalSteps.0.acted_by_user.data.id', $this->internalControl->id)
            ->assertJsonPath('props.approvalSteps.1.assignee_user.data.id', $this->director->id);
        Notification::assertSentTo($this->director, VendorReportApprovalRequired::class);
        Notification::assertNotSentTo($this->otherDirector, VendorReportApprovalRequired::class);

        $this->approveAs($id, $this->director);
        $page = $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'APPROVED')
            ->assertJsonPath('props.currentStep', null)
            ->assertJsonCount(2, 'props.approvalSteps')
            ->assertJsonPath('props.approvalSteps.0.status', 'APPROVED')
            ->assertJsonPath('props.approvalSteps.1.status', 'APPROVED')
            ->assertJsonPath('props.approvalSteps.1.acted_by_user.data.id', $this->director->id);
        $this->assertNotNull($page->json('props.report.data.approved_at'));
        $this->assertCount(2, collect($page->json('props.activities'))->where('description', 'approved'));
        Notification::assertSentTo($this->requester, VendorReportApproved::class);
    }

    public function test_ac02_submit_without_department_head_starts_exactly_two_pending_steps(): void
    {
        $id = $this->createDraft();
        $this->submitAsRequester($id);

        $this->assertAtInternalControl($id);
    }

    #[DataProvider('invalidDepartments')]
    public function test_ac03_invalid_creator_department_leaves_the_draft_untouched(string $state): void
    {
        $id = $this->createDraft();
        if ($state === 'missing') {
            $this->requester->update(['department_id' => null]);
        } else {
            $this->department->update(['is_active' => false]);
        }

        $this->actingAs($this->requester)->post(route('vendor-reports.submit', $id))->assertSessionHas('error');
        $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'DRAFT')
            ->assertJsonPath('props.report.data.code', null)
            ->assertJsonPath('props.currentStep', null)
            ->assertJsonCount(0, 'props.approvalSteps');
        Notification::assertNothingSent();
    }

    public static function invalidDepartments(): array
    {
        return ['missing department' => ['missing'], 'inactive department' => ['inactive']];
    }

    public function test_ac04_creator_with_both_approval_roles_still_needs_two_manual_actions(): void
    {
        $this->requester->assignRole(['internal_control', 'bod']);
        $id = $this->submitDraftWithDepartmentHead();

        $this->assertAtInternalControl($id);
    }

    #[DataProvider('invalidDirectors')]
    public function test_ac05_invalid_director_selection_cannot_advance_internal_control(string $selection): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $selectedId = $this->director->id;
        if ($selection === 'missing') {
            $selectedId = null;
        } elseif ($selection === 'wrong_role') {
            $selectedId = $this->requester->id;
        } elseif ($selection === 'inactive') {
            $this->director->update(['is_active' => false]);
        } else {
            $this->director->delete();
        }
        Notification::fake();

        $this->actingAs($this->internalControl)
            ->post(route('vendor-reports.approve', $id), ['selected_next_approver_id' => $selectedId])
            ->assertRedirect();

        $this->assertTrue(session()->has('error') || session()->has('errors'), 'Invalid director must produce an error.');
        $this->assertAtInternalControl($id);
        Notification::assertNothingSent();
    }

    public static function invalidDirectors(): array
    {
        return [
            'no director selected' => ['missing'],
            'requester instead of director' => ['wrong_role'],
            'inactive director' => ['inactive'],
            'deleted director' => ['deleted'],
        ];
    }

    public function test_ac07_only_the_current_assignee_can_approve(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $head = User::findOrFail($this->department->head_user_id);

        foreach ([$this->director, $head, $this->requester] as $unauthorized) {
            $this->actingAs($unauthorized)->post(route('vendor-reports.approve', $id))->assertForbidden();
        }
        $this->assertAtInternalControl($id);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        foreach ([$this->otherDirector, $head, $this->internalControl] as $unauthorized) {
            $this->actingAs($unauthorized)->post(route('vendor-reports.approve', $id))->assertForbidden();
        }
        $this->showReport($id, $this->director)->assertJsonPath('props.canApprove', true);
        $this->showReport($id, $head)->assertJsonPath('props.canApprove', false);
    }

    #[DataProvider('rejectionStages')]
    public function test_ac09_rejection_is_terminal_and_removes_pending_work(string $stage): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $approver = $this->internalControl;
        if ($stage === 'director') {
            $this->approveAs($id, $this->internalControl, $this->director->id);
            $approver = $this->director;
        }

        $this->actingAs($approver)
            ->post(route('vendor-reports.reject', $id), ['rejection_note' => 'Báo giá chưa đầy đủ'])
            ->assertSessionHas('success');
        $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'REJECTED')
            ->assertJsonPath('props.report.data.rejected_note', 'Báo giá chưa đầy đủ')
            ->assertJsonPath('props.currentStep', null);
        $this->actingAs($approver)->post(route('vendor-reports.approve', $id))->assertForbidden();

        foreach ([$this->internalControl, $this->director, $this->otherDirector] as $user) {
            $this->actingAs($user)->getJson(route('dashboard.pending-actions'))->assertJsonCount(0, 'data');
        }
    }

    public static function rejectionStages(): array
    {
        return ['internal control rejects' => ['internal_control'], 'director rejects' => ['director']];
    }

    public function test_ac10_clone_retains_workflow_and_history_then_starts_new_steps(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->actingAs($this->internalControl)
            ->post(route('vendor-reports.reject', $id), ['rejection_note' => 'Cần sửa báo giá'])
            ->assertSessionHas('success');
        $originalSteps = $this->showReport($id)->json('props.approvalSteps');

        $clone = $this->actingAs($this->requester)->post(route('vendor-reports.clone', $id))
            ->assertRedirect();
        $editPage = $this->get($clone->headers->get('Location'), ['X-Inertia' => 'true'])->assertOk();
        $cloneId = $editPage->json('props.report.data.id');
        $editPage->assertJsonPath('props.report.data.workflow_type', 'INTERNAL_CONTROL_BOD')
            ->assertJsonPath('props.report.data.status', 'DRAFT')
            ->assertJsonPath('props.report.data.parent_id', $id)
            ->assertJsonPath('props.report.data.root_id', $id);

        $this->put(route('vendor-reports.update', $cloneId), $this->draftPayload())->assertSessionHasNoErrors();
        $this->submitAsRequester($cloneId);
        $this->assertAtInternalControl($cloneId);
        $this->assertSame($originalSteps, $this->showReport($id)->json('props.approvalSteps'));
    }

    #[DataProvider('missingApproverRoles')]
    public function test_ac11_submit_requires_active_internal_control_and_director(string $role): void
    {
        $this->assignDepartmentHead();
        foreach (User::role($role)->get() as $user) {
            $user->update(['is_active' => false]);
        }
        $id = $this->createDraft();

        $this->post(route('vendor-reports.submit', $id))->assertSessionHas('error');
        $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'DRAFT')
            ->assertJsonPath('props.report.data.code', null)
            ->assertJsonCount(0, 'props.approvalSteps');
        Notification::assertNothingSent();
    }

    public static function missingApproverRoles(): array
    {
        return ['no internal control' => ['internal_control'], 'no director' => ['bod']];
    }

    #[DataProvider('mixedApproverRoles')]
    public function test_ac12_other_roles_do_not_hide_assigned_approval_work(string $role, string $additionalRole): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $approver = $this->internalControl;
        if ($role === 'bod') {
            $this->approveAs($id, $this->internalControl, $this->director->id);
            $approver = $this->director;
        }
        $approver->assignRole($additionalRole);
        $this->createDraft('NORMAL'); // A separate draft must remain private to its creator.

        $this->actingAs($approver)->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(1, 'props.reports.data')
            ->assertJsonPath('props.reports.data.0.id', $id);
        $this->showReport($id, $approver)->assertJsonPath('props.canApprove', true);
        $this->actingAs($approver)->getJson(route('dashboard.pending-actions'))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);
        $this->assertPendingMetric($approver, 1);
    }

    public static function mixedApproverRoles(): array
    {
        return [
            'requester and internal control' => ['internal_control', 'requester'],
            'requester and director' => ['bod', 'requester'],
            'purchasing admin and internal control' => ['internal_control', 'purchasing_admin'],
            'purchasing admin and director' => ['bod', 'purchasing_admin'],
            'accountant and internal control' => ['internal_control', 'accountant'],
            'accountant and director' => ['bod', 'accountant'],
        ];
    }

    public function test_ac13_repeated_submit_and_approval_do_not_duplicate_transitions(): void
    {
        // Sequential replay coverage; simultaneous locking must be tested on the deployment DB engine.
        $id = $this->submitDraftWithDepartmentHead();
        $this->actingAs($this->requester)->post(route('vendor-reports.submit', $id))->assertForbidden();
        $this->assertAtInternalControl($id);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->actingAs($this->internalControl)
            ->post(route('vendor-reports.approve', $id), ['selected_next_approver_id' => $this->otherDirector->id])
            ->assertForbidden();
        $page = $this->showReport($id)
            ->assertJsonCount(2, 'props.approvalSteps')
            ->assertJsonPath('props.approvalSteps.1.assignee_user.data.id', $this->director->id);
        $this->assertCount(1, collect($page->json('props.activities'))->where('description', 'approved'));
        Notification::assertSentToTimes($this->internalControl, VendorReportSubmitted::class, 1);
        Notification::assertSentToTimes($this->director, VendorReportApprovalRequired::class, 1);
    }

    public function test_ac14_failed_approval_rolls_back_state_audit_and_notifications(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $before = $this->showReport($id)->json('props.activities');
        // Exercise real queue/after-commit handling, with email captured in memory only.
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        $mailTransport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $mailTransport);
        Notification::swap(new ChannelManager($this->app));

        // Inject a storage failure after validation through the real database boundary.
        DB::unprepared("CREATE TRIGGER reject_approval_audit BEFORE INSERT ON activity_log
            WHEN NEW.description = 'approved'
            BEGIN SELECT RAISE(ABORT, 'Simulated audit storage failure'); END");
        try {
            $this->actingAs($this->internalControl)
                ->post(route('vendor-reports.approve', $id), ['selected_next_approver_id' => $this->director->id])
                ->assertSessionHas('error');
        } finally {
            DB::unprepared('DROP TRIGGER reject_approval_audit');
        }

        $this->assertAtInternalControl($id);
        $this->assertSame($before, $this->showReport($id)->json('props.activities'));
        $this->actingAs($this->director)->getJson(route('notifications.unread-count'))
            ->assertJsonPath('count', 0);
        $this->assertCount(0, $mailTransport->messages(), 'A rolled-back approval must not send email.');
    }

    public function test_ac15_dashboard_and_print_follow_the_two_step_timeline(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->actingAs($this->internalControl)->getJson(route('dashboard.pending-actions'))->assertJsonCount(1, 'data');
        $this->actingAs($this->director)->getJson(route('dashboard.pending-actions'))->assertJsonCount(0, 'data');
        $this->assertPendingMetric($this->internalControl, 1);
        $this->assertPendingMetric($this->director, 0);
        $this->actingAs($this->requester)
            ->get(route('vendor-reports.index', ['workflow_type' => 'INTERNAL_CONTROL_BOD']), ['X-Inertia' => 'true'])
            ->assertJsonCount(1, 'props.reports.data')
            ->assertJsonPath('props.reports.data.0.id', $id);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->actingAs($this->director)->getJson(route('dashboard.pending-actions'))->assertJsonCount(1, 'data');
        $this->assertPendingMetric($this->internalControl, 0);
        $this->assertPendingMetric($this->director, 1);
        $this->approveAs($id, $this->director);
        $this->actingAs($this->director)->getJson(route('dashboard.pending-actions'))->assertJsonCount(0, 'data');
        $this->assertPendingMetric($this->internalControl, 0);
        $this->assertPendingMetric($this->director, 0);
        $this->actingAs($this->requester)->get(route('vendor-reports.print', $id))
            ->assertOk()
            ->assertSee('KSNB → Giám đốc (bỏ qua Trưởng phòng)')
            ->assertSee($this->internalControl->name)
            ->assertSee($this->director->name);
    }

    #[DataProvider('legacyWorkflows')]
    public function test_ac16_legacy_workflows_keep_their_runtime_steps(string $workflow, array $stepKeys): void
    {
        $this->assignDepartmentHead();
        $id = $this->createDraft($workflow);
        $this->submitAsRequester($id);

        $page = $this->showReport($id)->assertJsonPath('props.report.data.workflow_type', $workflow);
        $this->assertSame($stepKeys, array_column($page->json('props.approvalSteps'), 'step_key'));
    }

    public static function legacyWorkflows(): array
    {
        return [
            'normal' => ['NORMAL', ['DEPT_HEAD', 'INTERNAL_CONTROL', 'BOD']],
            'two directors' => ['SPECIAL_1', ['DEPT_HEAD', 'INTERNAL_CONTROL', 'BOD_1', 'BOD_2']],
            'national purchasing' => ['SPECIAL_2', ['DEPT_HEAD', 'NATIONAL_PURCHASING', 'INTERNAL_CONTROL', 'BOD']],
            'technical board' => ['SPECIAL_3', ['DEPT_HEAD', 'TECH_BOARD', 'INTERNAL_CONTROL', 'BOD']],
            'urgent' => ['URGENT', ['DEPT_HEAD', 'BOD']],
            'existing head bypass' => ['SPECIAL_4', ['INTERNAL_CONTROL', 'BOD_1', 'BOD_2']],
        ];
    }

    public function test_ac16_legacy_workflow_with_head_step_still_requires_a_department_head(): void
    {
        $id = $this->createDraft('NORMAL');
        $this->post(route('vendor-reports.submit', $id))->assertSessionHas('error');
        $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'DRAFT')
            ->assertJsonCount(0, 'props.approvalSteps');
    }

    public function test_existing_special_4_can_submit_without_a_department_head(): void
    {
        $id = $this->createDraft('SPECIAL_4');
        $this->submitAsRequester($id);
        $page = $this->showReport($id)->assertJsonPath('props.currentStep.data.step_key', 'INTERNAL_CONTROL');
        $this->assertSame(['INTERNAL_CONTROL', 'BOD_1', 'BOD_2'], array_column($page->json('props.approvalSteps'), 'step_key'));
    }

    public function test_submit_notifies_only_active_non_deleted_internal_control_users(): void
    {
        $inactive = $this->userWithRole('internal_control');
        $inactive->update(['is_active' => false]);
        $deleted = $this->userWithRole('internal_control');
        $deleted->delete();
        $this->submitDraftWithDepartmentHead();
        Notification::assertSentToTimes($this->internalControl, VendorReportSubmitted::class, 1);
        Notification::assertNotSentTo($inactive, VendorReportSubmitted::class);
        Notification::assertNotSentTo($deleted, VendorReportSubmitted::class);
    }

    public function test_stale_draft_cannot_be_submitted_twice_through_the_service(): void
    {
        $this->assignDepartmentHead();
        $id = $this->createDraft();
        $stale = VendorReport::findOrFail($id);
        $this->submitAsRequester($id);
        Notification::fake();
        $this->assertOperationRejected(fn () => app(VendorReportSubmissionService::class)->submit($stale));
        $this->assertAtInternalControl($id);
        Notification::assertNothingSent();
    }

    public function test_stale_approval_cannot_approve_the_next_step_even_when_actor_holds_both_roles(): void
    {
        $this->internalControl->assignRole('bod');
        $id = $this->submitDraftWithDepartmentHead();
        $stale = VendorReport::findOrFail($id);
        $this->approveAs($id, $this->internalControl, $this->internalControl->id);
        Notification::fake();
        $this->assertOperationRejected(fn () => app(VendorReportApprovalService::class)->approve($stale, $this->internalControl));
        $this->showReport($id)->assertJsonPath('props.currentStep.data.step_key', 'BOD')
            ->assertJsonPath('props.approvalSteps.1.status', 'PENDING');
        Notification::assertNothingSent();
    }

    public function test_stale_rejection_cannot_reject_the_next_step_for_a_dual_role_actor(): void
    {
        $this->internalControl->assignRole('bod');
        $id = $this->submitDraftWithDepartmentHead();
        $stale = VendorReport::findOrFail($id);
        $this->approveAs($id, $this->internalControl, $this->internalControl->id);
        Notification::fake();
        $this->assertOperationRejected(fn () => app(VendorReportApprovalService::class)->reject($stale, $this->internalControl, 'Stale rejection'));
        $this->showReport($id)->assertJsonPath('props.currentStep.data.step_key', 'BOD');
        Notification::assertNothingSent();
    }

    public function test_stale_cancel_does_not_override_a_concurrent_approval(): void
    {
        Role::create(['name' => 'admin_system', 'guard_name' => 'web']);
        $admin = $this->userWithRole('admin_system');
        $id = $this->submitDraftWithDepartmentHead();
        $stale = VendorReport::findOrFail($id);
        $this->approveAs($id, $this->internalControl, $this->director->id);
        Notification::fake();
        $this->assertOperationRejected(fn () => app(VendorReportApprovalService::class)->cancel($stale, $admin, 'Stale cancellation'));
        $this->showReport($id)->assertJsonPath('props.currentStep.data.step_key', 'BOD');
        Notification::assertNothingSent();
    }

    public function test_stale_approval_and_rejection_cannot_reverse_cancellation(): void
    {
        Role::create(['name' => 'admin_system', 'guard_name' => 'web']);
        $admin = $this->userWithRole('admin_system');
        $id = $this->submitDraftWithDepartmentHead();
        $stale = VendorReport::findOrFail($id);
        app(VendorReportApprovalService::class)->cancel(VendorReport::findOrFail($id), $admin, 'Cancelled');
        Notification::fake();
        $service = app(VendorReportApprovalService::class);
        $this->assertOperationRejected(fn () => $service->approve($stale, $this->internalControl, null, $this->director->id));
        $this->assertOperationRejected(fn () => $service->reject($stale, $this->internalControl, 'Stale rejection'));
        $this->showReport($id)->assertJsonPath('props.report.data.status', 'CANCELED')->assertJsonPath('props.currentStep', null);
        Notification::assertNothingSent();
    }

    public function test_service_reloads_an_actor_who_was_deactivated_after_being_loaded(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        User::findOrFail($this->internalControl->id)->update(['is_active' => false]);
        Notification::fake();
        $this->assertOperationRejected(fn () => app(VendorReportApprovalService::class)->approve(
            VendorReport::findOrFail($id), $this->internalControl, null, $this->director->id
        ));
        $this->assertAtInternalControl($id);
        Notification::assertNothingSent();
    }

    public function test_director_cannot_inject_an_unsolicited_next_assignee(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->approveAs($id, $this->internalControl, $this->director->id);
        Notification::fake();
        $this->actingAs($this->director)->post(route('vendor-reports.approve', $id), [
            'selected_next_approver_id' => $this->otherDirector->id,
        ])->assertSessionHas('error');
        $this->showReport($id)->assertJsonPath('props.currentStep.data.step_key', 'BOD')
            ->assertJsonPath('props.approvalSteps.1.status', 'PENDING');
        Notification::assertNothingSent();
    }

    public function test_active_assigned_director_retains_snapshot_access_after_role_is_removed(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->director->removeRole('bod');
        $this->actingAs($this->director)->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonCount(1, 'props.reports.data')->assertJsonPath('props.reports.data.0.id', $id);
        $this->getJson(route('dashboard.pending-actions'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->showReport($id, $this->director)->assertJsonPath('props.canApprove', true);
        $this->approveAs($id, $this->director);
        $this->showReport($id, $this->director)->assertJsonPath('props.report.data.status', 'APPROVED');
        $this->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonCount(1, 'props.reports.data')->assertJsonPath('props.reports.data.0.id', $id);
        $this->getJson(route('dashboard.pending-actions'))->assertJsonCount(0, 'data');
    }

    public function test_dashboard_ignores_pending_steps_without_a_current_active_report(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        // A legacy/inconsistent snapshot must not become an actionable approval.
        VendorReport::findOrFail($id)->update(['current_step_id' => null]);
        $this->actingAs($this->internalControl)->getJson(route('dashboard.pending-actions'))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_notification_bell_links_to_the_report_for_current_approvers_only(): void
    {
        config(['mail.default' => 'array', 'queue.default' => 'sync']);
        Notification::swap(new ChannelManager($this->app));
        $id = $this->submitDraftWithDepartmentHead();

        $this->actingAs($this->internalControl)->getJson(route('notifications.unread-count'))->assertJsonPath('count', 1);
        $this->getJson(route('notifications.index'))
            ->assertJsonPath('data.data.0.data.report_id', $id)
            ->assertJsonPath('data.data.0.data.action_url', "/vendor-reports/{$id}");
        $this->actingAs($this->director)->getJson(route('notifications.unread-count'))->assertJsonPath('count', 0);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->actingAs($this->director)->getJson(route('notifications.unread-count'))->assertJsonPath('count', 1);
        $this->getJson(route('notifications.index'))
            ->assertJsonPath('data.data.0.data.report_id', $id)
            ->assertJsonPath('data.data.0.data.step_order', 2)
            ->assertJsonPath('data.data.0.data.previous_approver', $this->internalControl->name)
            ->assertJsonPath('data.data.0.data.action_url', "/vendor-reports/{$id}");
        $this->showReport($id, $this->director)->assertJsonPath('props.canApprove', true);
        $this->actingAs($this->otherDirector)->getJson(route('notifications.unread-count'))->assertJsonPath('count', 0);
    }

    public function test_dashboard_trend_includes_a_completed_report_in_the_current_month(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 12:00:00'));
        $id = $this->submitDraftWithDepartmentHead();
        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->approveAs($id, $this->director);

        $this->actingAs($this->requester)->getJson(route('dashboard.chart-data', ['type' => 'trend']))
            ->assertOk()
            ->assertJsonPath('data.labels', ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'])
            ->assertJsonPath('data.datasets.0.data', [0, 0, 0, 0, 0, 1])
            ->assertJsonPath('data.datasets.1.data', [0, 0, 0, 0, 0, 0]);
        $this->travelBack();
    }

    public function test_dashboard_links_and_activity_do_not_expose_reports_the_viewer_cannot_open(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->actingAs($this->director)->getJson(route('dashboard.activities'))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->approveAs($id, $this->director);

        $purchasingAdmin = $this->userWithRole('purchasing_admin');
        $this->actingAs($purchasingAdmin)->getJson(route('dashboard.pending-actions'))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('dashboard.activities'))->assertOk()->assertJsonCount(0, 'data');
        $this->get(route('vendor-reports.show', $id))->assertForbidden();

        VendorReport::findOrFail($id)->update(['purchasing_admin_id' => $purchasingAdmin->id]);
        $this->getJson(route('dashboard.pending-actions'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $activities = $this->getJson(route('dashboard.activities'))->assertOk()->json('data');
        $this->assertNotEmpty($activities);
        $this->assertSame([$id], collect($activities)->pluck('report_id')->unique()->values()->all());
        $this->showReport($id, $purchasingAdmin)->assertJsonPath('props.report.data.status', 'APPROVED');
    }

    public function test_director_selector_keeps_selection_required_when_all_candidates_become_inactive(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $this->showReport($id, $this->internalControl)
            ->assertJsonCount(2, 'props.selectableApprovers')
            ->assertJsonPath('props.currentStep.data.requires_selection', true);

        $this->director->update(['is_active' => false]);
        $this->otherDirector->delete();
        $this->showReport($id, $this->internalControl)
            ->assertJsonCount(0, 'props.selectableApprovers')
            ->assertJsonPath('props.currentStep.data.requires_selection', true)
            ->assertJsonPath('props.canApprove', true);
        $this->post(route('vendor-reports.approve', $id), [
            'selected_next_approver_id' => $this->director->id,
        ])->assertSessionHas('error');
        $this->assertAtInternalControl($id);
    }

    public function test_only_selected_director_sees_report_and_no_head_or_early_director_gets_pending_work(): void
    {
        $id = $this->submitDraftWithDepartmentHead();
        $privateDraft = $this->createDraft();
        $head = User::findOrFail($this->department->head_user_id);
        foreach ([$head, $this->director, $this->otherDirector] as $user) {
            $this->actingAs($user)->getJson(route('dashboard.pending-actions'))
                ->assertOk()->assertJsonCount(0, 'data');
        }
        $this->showReport($privateDraft, $head)->assertJsonPath('props.canApprove', false);

        $this->approveAs($id, $this->internalControl, $this->director->id);
        $this->actingAs($this->otherDirector)->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonCount(0, 'props.reports.data');
        $this->get(route('vendor-reports.show', $id))->assertForbidden();
        $this->get(route('vendor-reports.show', $privateDraft))->assertForbidden();
        $this->getJson(route('dashboard.pending-actions'))->assertJsonCount(0, 'data');
        $this->actingAs($this->director)->get(route('vendor-reports.index'), ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonCount(1, 'props.reports.data')->assertJsonPath('props.reports.data.0.id', $id);
    }

    private function assertPendingMetric(User $user, int $expected): void
    {
        $metrics = $this->actingAs($user)->getJson(route('dashboard.metrics'))->assertOk()->json('data');
        $this->assertSame($expected, collect($metrics)->firstWhere('id', 'pending_approval')['value'] ?? null);
    }

    private function assertOperationRejected(callable $operation): void
    {
        try {
            $operation();
        } catch (\Exception $exception) {
            $this->assertNotEmpty($exception->getMessage());

            return;
        }
        $this->fail('The operation must reject stale or unauthorized input.');
    }

    private function assignDepartmentHead(): void
    {
        $this->department->update(['head_user_id' => $this->userWithRole('dept_head')->id]);
    }

    private function submitDraftWithDepartmentHead(): int
    {
        $this->assignDepartmentHead();
        $id = $this->createDraft();
        $this->submitAsRequester($id);

        return $id;
    }

    private function submitAsRequester(int $id): void
    {
        $this->actingAs($this->requester)->post(route('vendor-reports.submit', $id))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertSessionHas('success');
    }

    private function approveAs(int $id, User $approver, ?int $selectedId = null): void
    {
        $this->actingAs($approver)->post(route('vendor-reports.approve', $id), [
            'note' => 'Đồng ý',
            'selected_next_approver_id' => $selectedId,
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success');
    }

    private function assertAtInternalControl(int $id): void
    {
        $page = $this->showReport($id)
            ->assertJsonPath('props.report.data.status', 'IN_APPROVAL')
            ->assertJsonPath('props.currentStep.data.step_key', 'INTERNAL_CONTROL')
            ->assertJsonCount(2, 'props.approvalSteps')
            ->assertJsonPath('props.approvalSteps.0.status', 'PENDING')
            ->assertJsonPath('props.approvalSteps.1.status', 'PENDING');
        $this->assertSame(['INTERNAL_CONTROL', 'BOD'], array_column($page->json('props.approvalSteps'), 'step_key'));
    }

    private function createDraft(string $workflow = 'INTERNAL_CONTROL_BOD'): int
    {
        $title = 'Workflow draft '.++$this->draftNumber;
        $this->actingAs($this->requester)
            ->post(route('vendor-reports.store'), array_merge($this->draftPayload($workflow), ['title' => $title]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('vendor-reports.index'));

        return $this->get(route('vendor-reports.index', ['search' => $title]), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(1, 'props.reports.data')
            ->json('props.reports.data.0.id');
    }

    private function showReport(int $id, ?User $viewer = null): TestResponse
    {
        return $this->actingAs($viewer ?? $this->requester)
            ->get(route('vendor-reports.show', $id), ['X-Inertia' => 'true'])
            ->assertOk();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function draftPayload(string $workflow = 'INTERNAL_CONTROL_BOD'): array
    {
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aRCkAAAAASUVORK5CYII=');

        return [
            'title' => 'Đề nghị mua vật tư',
            'workflow_type' => $workflow,
            'report_image' => UploadedFile::fake()->createWithContent('report.png', $image),
            'quotation_files' => [UploadedFile::fake()->create('quotation.pdf', 1, 'application/pdf')],
        ];
    }
}
