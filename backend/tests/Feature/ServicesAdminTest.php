<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServicesAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_draft_submit_and_role_controlled_review(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $id = $this->actingAs($u)->postJson('/api/v1/service-requests', ['kind' => 'application', 'subject' => 'Synthetic application', 'form_data' => ['applicant_name' => 'Demo', 'contact_phone' => '000000', 'service_address' => 'Synthetic address', 'connection_type' => 'residential'], 'status' => 'approved'])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');
        $this->postJson('/api/v1/service-requests/'.$id.'/submit')->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->actingAs(User::factory()->create())->getJson('/api/v1/service-requests/'.$id)->assertForbidden();
        $support = User::factory()->create(['role' => 'support']);
        $this->actingAs($support)->postJson('/api/v1/admin/service-requests/'.$id.'/review', ['status' => 'under_review', 'message' => 'Reviewing documents'])->assertOk();
        $this->postJson('/api/v1/admin/service-requests/'.$id.'/review', ['status' => 'approved', 'message' => 'Approved application'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/v1/admin/service-requests/'.$id.'/review', ['status' => 'approved', 'message' => 'Approved application'])->assertOk();
    }

    public function test_upload_validation_and_private_download(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $s = ServiceRequest::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'reference' => 'REQ-demo', 'kind' => 'complaint', 'subject' => 'Demo issue', 'status' => 'draft']);
        $this->actingAs($u)->postJson('/api/v1/service-requests/'.$s->id.'/attachments', ['file' => UploadedFile::fake()->createWithContent('unsafe.php', '<?php echo 1;')])->assertUnprocessable();
        $id = $this->postJson('/api/v1/service-requests/'.$s->id.'/attachments', ['file' => UploadedFile::fake()->image('safe.png', 10, 10)])->assertCreated()->json('data.id');
        $this->actingAs(User::factory()->create())->get('/api/v1/attachments/'.$id)->assertForbidden();
        $this->actingAs($u)->get('/api/v1/attachments/'.$id)->assertOk()->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_role_changes_and_exports_are_audited_and_permission_controlled(): void
    {
        $client = User::factory()->create();
        $this->actingAs($client)->getJson('/api/v1/admin/summary')->assertForbidden();
        $this->getJson('/api/v1/admin/exports/payments')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->patchJson('/api/v1/admin/users/'.$client->id.'/role', ['role' => 'support'])->assertOk();
        $this->assertSame('support', $client->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.role_changed']);
        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/role', ['role' => 'client'])->assertConflict();
        $this->get('/api/v1/admin/exports/payments')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'payments.export']);
    }

    public function test_formula_injection_protection(): void
    {
        foreach (['=1+1', '+CMD', '-1', '@SUM(A1)', '  =1', "\tformula"] as $v) {
            $this->assertStringStartsWith("'", Csv::cell($v));
        }$this->assertSame('000007', Csv::cell('000007'));
    }

    public function test_profile_correction_does_not_change_upstream_or_other_accounts(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson('/api/v1/service-requests', ['kind' => 'complaint', 'subject' => 'Correction', 'category' => 'profile_correction', 'description' => 'Please review this synthetic record', 'external_account_id' => '000008'])->assertNotFound();
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_oversized_and_active_pdf_uploads_are_rejected(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $s = ServiceRequest::create(['id' => (string) Str::uuid(), 'user_id' => $u->id, 'reference' => 'REQ-unsafe-demo', 'kind' => 'complaint', 'subject' => 'Demo issue', 'status' => 'draft']);
        $this->actingAs($u)->postJson('/api/v1/service-requests/'.$s->id.'/attachments', ['file' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertUnprocessable();
        $this->postJson('/api/v1/service-requests/'.$s->id.'/attachments', ['file' => UploadedFile::fake()->createWithContent('active.pdf', "%PDF-1.4\n/JavaScript (alert)\n%%EOF")])->assertUnprocessable();
        $this->assertDatabaseCount('attachments', 0);
    }
}
