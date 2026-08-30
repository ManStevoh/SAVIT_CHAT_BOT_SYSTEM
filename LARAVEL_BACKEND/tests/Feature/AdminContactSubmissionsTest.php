<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminContactSubmissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contact_form_stores_submission(): void
    {
        $this->postJson('/api/contact', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'I want a demo of RelayIQ.',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'I want a demo of RelayIQ.',
        ]);
    }

    public function test_admin_can_list_mark_read_and_delete_submissions(): void
    {
        $row = ContactSubmission::create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'message' => 'Hello',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/contact-submissions')
            ->assertOk()
            ->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('submissions.0.email', 'ada@example.com');

        $this->postJson('/api/admin/contact-submissions/'.$row->id.'/read')
            ->assertOk()
            ->assertJsonPath('submission.isRead', true);

        $this->assertNotNull($row->fresh()?->read_at);

        $this->deleteJson('/api/admin/contact-submissions/'.$row->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('contact_submissions', ['id' => $row->id]);
    }

    public function test_company_user_cannot_list_contact_submissions(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'company_admin',
            'email_verified_at' => now(),
        ]));

        $this->getJson('/api/admin/contact-submissions')->assertForbidden();
    }
}
