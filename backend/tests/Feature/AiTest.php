<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewContactMessage;
use App\Services\Ai\AiManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function reply(string $text): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]]];
    }

    private function configure(array $chain = ['groq'], bool $chat = true): void
    {
        app(AiManager::class)->update([
            'chain' => $chain,
            'providers' => collect($chain)->mapWithKeys(fn ($c) => [$c => ['api_key' => "key-$c"]])->all(),
            'chat' => ['enabled' => $chat],
        ]);
    }

    public function test_api_keys_are_encrypted_and_never_returned(): void
    {
        Sanctum::actingAs(User::first());
        $this->putJson('/api/admin/ai', ['chain' => ['groq'], 'providers' => ['groq' => ['api_key' => 'gsk_secret1234']]])
            ->assertOk()->assertJsonPath('enabled', true);

        $json = $this->getJson('/api/admin/ai')->assertOk()->getContent();
        $this->assertStringNotContainsString('gsk_secret1234', $json);
        $this->assertStringContainsString('1234', $json); // hint only
        $this->assertStringNotContainsString('gsk_secret1234', json_encode(Setting::get('ai')));

        // a blank key keeps the stored one, remove_key deletes it
        $this->putJson('/api/admin/ai', ['providers' => ['groq' => ['api_key' => '', 'model' => 'x']]])->assertJsonPath('enabled', true);
        $this->putJson('/api/admin/ai', ['providers' => ['groq' => ['remove_key' => true]]])->assertJsonPath('enabled', false);
    }

    public function test_guests_cannot_use_admin_ai(): void
    {
        $this->getJson('/api/admin/ai')->assertUnauthorized();
        $this->postJson('/api/admin/ai/assist', ['task' => 'improve', 'text' => 'x'])->assertUnauthorized();
    }

    public function test_falls_back_to_the_next_provider_when_one_fails(): void
    {
        $this->configure(['groq', 'gemini']);
        Http::fake([
            'api.groq.com/*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
            'generativelanguage.googleapis.com/*' => Http::response($this->reply('Hello from Gemini')),
        ]);

        $out = app(AiManager::class)->complete('sys', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['text' => 'Hello from Gemini', 'provider' => 'gemini', 'model' => 'gemini-2.5-flash'], $out);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'groq') && $r->hasHeader('Authorization', 'Bearer key-groq'));
    }

    public function test_admin_can_translate_and_improve_text(): void
    {
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::response($this->reply('سلام دنیا'))]);
        Sanctum::actingAs(User::first());

        $this->postJson('/api/admin/ai/assist', ['task' => 'translate', 'text' => 'Hello world', 'from' => 'en', 'to' => 'fa'])
            ->assertOk()->assertJsonPath('text', 'سلام دنیا');
        Http::assertSent(fn (Request $r) => str_contains($r['messages'][0]['content'], 'from English to Persian') && $r['messages'][1]['content'] === 'Hello world');

        $this->postJson('/api/admin/ai/assist', ['task' => 'improve', 'lang' => 'fa'])->assertStatus(422);
    }

    public function test_ai_errors_are_reported_cleanly(): void
    {
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);
        Sanctum::actingAs(User::first());

        $this->postJson('/api/admin/ai/assist', ['task' => 'improve', 'text' => 'abc'])
            ->assertStatus(502)->assertJsonPath('message', 'Groq: HTTP 401: Invalid API Key');
    }

    public function test_skill_suggestions_are_parsed_from_json(): void
    {
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::response($this->reply("```json\n[{\"name\":\"Docker\",\"level\":150},{\"name\":\"Redis\"}]\n```"))]);
        Sanctum::actingAs(User::first());

        $this->postJson('/api/admin/ai/assist', ['task' => 'skills', 'context' => ['role' => 'Backend developer']])
            ->assertOk()->assertJsonPath('skills', [['name' => 'Docker', 'level' => 100], ['name' => 'Redis', 'level' => 70]]);
    }

    public function test_translate_missing_fills_only_empty_fields_and_saves(): void
    {
        $this->configure();
        $site = app(\App\Services\SiteContent::class)->get();
        $site['services'][0]['title'] = ['en' => 'Web development'];
        $site['members'][0]['bio'] = ['en' => 'Builds things.'];
        app(\App\Services\SiteContent::class)->sync($site);

        Http::fake(function (Request $r) {
            $items = json_decode($r['messages'][1]['content'], true);

            return Http::response($this->reply(json_encode(array_map(fn ($t) => "FA:$t", $items), JSON_UNESCAPED_UNICODE)));
        });
        Sanctum::actingAs(User::first());

        $this->postJson('/api/admin/ai/translate-missing', ['locale' => 'fa'])->assertOk()
            ->assertJsonPath('translated', 2)
            ->assertJsonPath('site.services.0.title.fa', 'FA:Web development')
            ->assertJsonPath('site.members.0.bio.fa', 'FA:Builds things.')
            ->assertJsonPath('site.members.1.bio.fa', 'عاشق رابط‌های کاربری روان و دسترس‌پذیر؛ متخصص React و انیمیشن وب.');
        $this->get('/fa')->assertSee('FA:Web development');
    }

    public function test_chat_is_hidden_and_closed_until_enabled(): void
    {
        $this->get('/')->assertDontSee('id="chat"', false);
        $this->postJson('/api/chat', ['message' => 'hi'])->assertNotFound();

        $this->configure(chat: true);
        $this->get('/')->assertSee('id="chat"', false);
    }

    public function test_chat_answers_from_site_knowledge_and_keeps_history(): void
    {
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::sequence()->push($this->reply('We build **web apps**.'))->push($this->reply('Sara leads frontend.'))]);

        $first = $this->postJson('/api/chat', ['message' => 'What do you do?', 'locale' => 'en'])->assertOk()
            ->assertJsonPath('reply', 'We build **web apps**.')->json('session_id');
        $this->postJson('/api/chat', ['session_id' => $first, 'message' => 'Who does frontend?'])->assertOk();

        Http::assertSent(function (Request $r) {
            $system = $r['messages'][0]['content'];

            return str_contains($system, 'Sara Mohammadi') && str_contains($system, 'Novin online store');
        });
        // second request carries the conversation so far
        $sent = Http::recorded()[1][0]['messages'];
        $this->assertCount(4, $sent);
        $this->assertSame(['What do you do?', 'We build **web apps**.', 'Who does frontend?'], array_column(array_slice($sent, 1), 'content'));
        $this->getJson("/api/chat/$first")->assertOk()->assertJsonCount(4, 'messages');
    }

    public function test_chat_turns_collected_details_into_a_lead(): void
    {
        Notification::fake();
        config(['mail.notify_address' => 'team@example.com']);
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::response($this->reply(
            "Thanks Reza! The team will email you soon.\n<lead>{\"name\":\"Reza\",\"email\":\"reza@example.com\",\"summary\":\"Wants an online shop.\"}</lead>"
        ))]);

        $this->postJson('/api/chat', ['message' => 'I am Reza, reza@example.com, I need a shop'])->assertOk()
            ->assertJsonPath('reply', 'Thanks Reza! The team will email you soon.')
            ->assertJsonPath('lead', true);

        $lead = ContactMessage::firstOrFail();
        $this->assertSame(['Reza', 'reza@example.com', 'Wants an online shop.'], [$lead->name, $lead->email, $lead->body]);
        $this->assertSame($lead->id, ChatSession::first()->contact_message_id);
        Notification::assertSentOnDemand(NewContactMessage::class);
    }

    public function test_chat_reports_unavailability_when_every_provider_fails(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response([], 500)]);

        $this->postJson('/api/chat', ['message' => 'hello'])->assertStatus(503)->assertJsonPath('message', 'unavailable');
    }

    public function test_admin_can_read_and_delete_chat_transcripts(): void
    {
        $this->configure();
        Http::fake(['api.groq.com/*' => Http::response($this->reply('Hi!'))]);
        $id = $this->postJson('/api/chat', ['message' => 'hello'])->json('session_id');

        Sanctum::actingAs(User::first());
        $this->getJson('/api/admin/chats')->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.preview', 'hello');
        $this->getJson("/api/admin/chats/$id")->assertOk()->assertJsonPath('messages.1.provider', 'groq');
        $this->getJson('/api/admin/chats')->assertJsonPath('unread', 0);
        $this->deleteJson("/api/admin/chats/$id")->assertOk();
        $this->assertDatabaseCount('chat_messages', 0);
    }
}
