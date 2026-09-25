<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteApiTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_public_site_returns_seeded_content(): void
    {
        $this->getJson('/api/site')->assertOk()
            ->assertJsonPath('team.name', 'تیم کدنگار')
            ->assertJsonCount(4, 'members')
            ->assertJsonPath('projects.0.members', ['m1', 'm2']);
    }

    public function test_login_returns_token_and_rejects_bad_password(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_guest_cannot_update_site(): void
    {
        $this->putJson('/api/admin/site', [])->assertUnauthorized();
    }

    public function test_admin_can_sync_site_and_relations_are_kept(): void
    {
        Sanctum::actingAs(User::first());
        $site = $this->getJson('/api/site')->json();

        $site['team']['name'] = 'تیم جدید';
        array_splice($site['members'], 1, 1); // remove m2
        $site['members'][] = ['id' => 'm9', 'name' => 'نفر جدید', 'level' => 'Senior', 'available' => true, 'skills' => [['name' => 'Laravel', 'level' => 90]]];
        $site['projects'][0]['members'] = ['m1', 'm2', 'm9'];

        $this->putJson('/api/admin/site', $site)->assertOk()
            ->assertJsonPath('team.name', 'تیم جدید')
            ->assertJsonPath('members.3.skills.0.name', 'Laravel')
            ->assertJsonPath('projects.0.members', ['m1', 'm9']);

        $this->getJson('/api/site')->assertJsonPath('team.name', 'تیم جدید')->assertJsonCount(4, 'members');
    }

    public function test_sync_validates_level(): void
    {
        Sanctum::actingAs(User::first());
        $site = $this->getJson('/api/site')->json();
        $site['members'][0]['level'] = 'Guru';

        $this->putJson('/api/admin/site', $site)->assertStatus(422)->assertJsonValidationErrors('members.0.level');
    }

    public function test_contact_message_flow(): void
    {
        $this->postJson('/api/contact', ['name' => 'Ali', 'email' => 'a@b.com', 'body' => 'سلام، پروژه داریم'])->assertCreated();
        $this->postJson('/api/contact', ['name' => 'Bot', 'email' => 'a@b.com', 'body' => 'spam spam', 'website' => 'x'])->assertStatus(422);

        $this->getJson('/api/admin/messages')->assertUnauthorized();
        Sanctum::actingAs(User::first());
        $this->getJson('/api/admin/messages')->assertOk()->assertJsonPath('unread', 1);

        $id = ContactMessage::first()->id;
        $this->patchJson("/api/admin/messages/{$id}/read")->assertOk();
        $this->getJson('/api/admin/messages')->assertJsonPath('unread', 0);
        $this->deleteJson("/api/admin/messages/{$id}")->assertOk();
    }

    public function test_image_upload(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::first());

        $url = $this->postJson('/api/admin/uploads', ['file' => UploadedFile::fake()->image('a.jpg')])
            ->assertCreated()->json('url');
        Storage::disk('public')->assertExists(str($url)->after('/storage/')->toString());
    }
}
