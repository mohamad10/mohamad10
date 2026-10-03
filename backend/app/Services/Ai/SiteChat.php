<?php

namespace App\Services\Ai;

use App\Models\ChatSession;
use App\Models\ContactMessage;
use App\Notifications\NewContactMessage;
use App\Services\SiteContent;
use App\Support\Locales;

/**
 * Visitor-facing assistant. Answers are grounded in the site content, and when a
 * visitor shares their name, email and project details the assistant emits a
 * <lead> block that is turned into a contact message for the admin.
 */
class SiteChat
{
    public const MAX_TURNS = 40;

    public function __construct(private AiManager $ai, private SiteContent $content) {}

    public function reply(ChatSession $session, string $text): array
    {
        $session->messages()->create(['role' => 'user', 'content' => $text]);
        $history = $session->messages()->reorder('id', 'desc')->limit(16)->get()->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();

        $result = $this->ai->complete($this->systemPrompt($session->locale), $history, 700);
        [$answer, $lead] = $this->extractLead($result['text']);

        if ($lead && ! $session->contact_message_id) {
            $msg = ContactMessage::create([
                'name' => $lead['name'], 'email' => $lead['email'],
                'subject' => 'AI chat lead', 'body' => $lead['summary'] ?: '(see chat transcript)', 'ip' => $session->ip,
            ]);
            $session->update(['contact_message_id' => $msg->id]);
            NewContactMessage::dispatchTo($msg);
        }
        $session->messages()->create(['role' => 'assistant', 'content' => $answer, 'provider' => $result['provider']]);

        return ['reply' => $answer, 'lead' => (bool) $lead];
    }

    public function systemPrompt(string $locale): string
    {
        $chat = $this->ai->settings()['chat'];
        $name = Locales::pick($chat['name'] ?? [], $locale) ?: 'Assistant';
        $lang = config("site.locales.$locale.name", 'English');
        $leads = ($chat['leads'] ?? true)
            ? "When a visitor wants a quote, a project or to talk to the team, politely ask for their name, email and a short description of the project (one question at a time). "
              ."Once you have all three, confirm to them that the team will get in touch, and append on its own line exactly: <lead>{\"name\":\"…\",\"email\":\"…\",\"summary\":\"…\"}</lead> (summary in English, max 3 sentences). Never show or mention this tag otherwise.\n"
            : "When a visitor wants to start a project, point them to the contact form at the bottom of the page.\n";

        return "You are {$name}, the friendly assistant on the website of the software team described below. "
            ."Answer visitors' questions about the team, its services, members, skills and past projects using ONLY the information below. "
            ."If something isn't covered, say you don't know and offer to connect them with the team — never invent prices, clients, dates or facts. "
            ."Politely decline unrelated requests (general coding help, homework, etc.). Keep replies short (1–4 sentences, simple markdown lists are fine). "
            ."Reply in the visitor's language; the page language is {$lang}.\n"
            .$leads
            .(filled($chat['instructions'] ?? '') ? "Extra instructions from the team: {$chat['instructions']}\n" : '')
            ."\n### Team knowledge\n".$this->knowledge($locale);
    }

    /** Compact plain-text summary of the site content in one language. */
    public function knowledge(string $locale): string
    {
        $s = $this->content->get();
        $t = fn ($v) => Locales::pick($v, $locale);
        $team = $s['team'];
        $lines = ["Team: {$t($team['name'] ?? '')} — {$t($team['tagline'] ?? '')}", "About: {$t($team['about'] ?? '')}"];
        $lines[] = 'Contact: '.collect([$team['email'] ?? null, $team['phone'] ?? null, $t($team['location'] ?? '')])->filter()->implode(' · ');
        foreach ($team['stats'] ?? [] as $st) {
            $lines[] = "Stat: {$st['value']} {$t($st['label'] ?? '')}";
        }
        $lines[] = "\nServices:";
        foreach ($s['services'] as $sv) {
            $lines[] = "- {$t($sv['title'])}: {$t($sv['desc'] ?? '')}";
        }
        $lines[] = "\nMembers:";
        foreach ($s['members'] as $m) {
            $skills = collect($m['skills'] ?? [])->map(fn ($k) => "{$k['name']} {$k['level']}%")->implode(', ');
            $lines[] = "- {$t($m['name'])}, {$t($m['role'] ?? '')} ({$m['level']}, {$m['years']} years".(! empty($m['available']) ? ', available' : ', busy')."). "
                ."{$t($m['bio'] ?? '')} Skills: {$skills}. Education: {$t($m['education'] ?? '')}. Languages: {$t($m['languages'] ?? '')}.";
        }
        $lines[] = "\nProjects:";
        foreach ($s['projects'] as $p) {
            $lines[] = "- {$t($p['title'])} ({$t($p['category'] ?? '')}, {$t($p['year'] ?? '')}): {$t($p['desc'] ?? '')} Tech: ".implode(', ', $p['tech'] ?? []).'.'
                .(filled($p['link'] ?? null) ? " Link: {$p['link']}" : '');
        }

        return implode("\n", $lines);
    }

    /** @return array{0: string, 1: ?array{name: string, email: string, summary: string}} */
    public function extractLead(string $text): array
    {
        $lead = null;
        if (preg_match('#<lead>\s*(\{.*?\})\s*</lead>#s', $text, $m)) {
            $data = json_decode($m[1], true);
            if (is_array($data) && filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) && filled($data['name'] ?? null)) {
                $lead = [
                    'name' => mb_substr(strip_tags($data['name']), 0, 120),
                    'email' => mb_substr($data['email'], 0, 190),
                    'summary' => mb_substr(strip_tags((string) ($data['summary'] ?? '')), 0, 2000),
                ];
            }
        }
        $clean = trim(preg_replace('#<lead>.*?</lead>#s', '', $text));

        return [$clean !== '' ? $clean : '✓', $lead];
    }
}
