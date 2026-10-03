<?php

namespace App\Services\Ai;

/** Prompt templates for the admin writing tools. */
class Assistant
{
    public function __construct(private AiManager $ai) {}

    private function lang(string $code): string
    {
        return config("site.locales.$code.name") ?? $code;
    }

    public function translate(string $text, string $from, string $to): string
    {
        return $this->run(
            "You are a professional translator for a software company's website. Translate the user's text from {$this->lang($from)} to {$this->lang($to)}. "
            .'Keep the meaning, tone and formatting. Keep technology names, product names, URLs, emails and numbers as they are. '
            .'For Persian use natural, fluent Persian with correct half-spaces (ZWNJ). Reply with the translation only — no quotes, notes or explanations.',
            $text, 2000,
        );
    }

    /** Translate many strings at once. @param array<string,string> $items @return array<string,string> */
    public function translateMany(array $items, string $from, string $to): array
    {
        $out = [];
        foreach (array_chunk($items, 20, true) as $chunk) {
            $json = $this->run(
                "You translate website content from {$this->lang($from)} to {$this->lang($to)}. The user sends a JSON object; "
                .'reply with a JSON object with exactly the same keys whose values are the translations. Keep technology and brand names, URLs and numbers unchanged. '
                .'For Persian use fluent Persian with correct half-spaces. Reply with JSON only.',
                json_encode($chunk, JSON_UNESCAPED_UNICODE), 4000,
            );
            $decoded = json_decode($this->stripFence($json), true);
            if (! is_array($decoded)) {
                throw new AiException('The AI returned an invalid response. Please try again.');
            }
            foreach ($chunk as $k => $_) {
                if (is_string($decoded[$k] ?? null) && $decoded[$k] !== '') {
                    $out[$k] = $decoded[$k];
                }
            }
        }

        return $out;
    }

    public function improve(string $text, string $lang, string $field = ''): string
    {
        return $this->run(
            "You are a senior copywriter for a software development team's portfolio website. Improve the user's {$field} text written in {$this->lang($lang)}: "
            .'fix grammar and spelling, make it clear, confident and professional, keep it about the same length, keep every fact, do not invent facts. '
            ."Write in {$this->lang($lang)}. Reply with the improved text only.",
            $text, 1500,
        );
    }

    public function generate(string $field, array $context, string $lang): string
    {
        return $this->run(
            "You write content for a software development team's portfolio website. Write the \"{$field}\" field in {$this->lang($lang)} "
            .'using only the facts in the JSON context the user sends (do not invent clients, numbers or achievements). '
            .'Bios and descriptions: 1–3 concise sentences. Short fields (titles, roles, categories): a few words. Reply with the text only.',
            json_encode($context, JSON_UNESCAPED_UNICODE), 800,
        );
    }

    /** @return list<array{name: string, level: int}> */
    public function suggestSkills(array $member): array
    {
        $json = $this->run(
            'Suggest the most relevant technical skills for this software team member, based on the JSON profile the user sends. '
            .'Reply with a JSON array of up to 8 objects {"name": "Skill", "level": 0-100} where level reflects the seniority and years of experience. '
            .'Do not repeat skills they already have. JSON only.',
            json_encode($member, JSON_UNESCAPED_UNICODE), 600,
        );
        $list = json_decode($this->stripFence($json), true);

        return collect(is_array($list) ? $list : [])->filter(fn ($s) => is_array($s) && filled($s['name'] ?? null))
            ->map(fn ($s) => ['name' => mb_substr((string) $s['name'], 0, 60), 'level' => max(0, min(100, (int) ($s['level'] ?? 70)))])
            ->take(8)->values()->all();
    }

    public function replyDraft(array $message, string $team): string
    {
        return $this->run(
            "You are the client-relations manager of the software team \"{$team}\". Draft a warm, professional email reply to the contact-form message the user sends. "
            .'Reply in the same language the sender wrote in. Thank them, address their request specifically, propose a next step (e.g. a short call) and sign as the team. '
            .'Do not promise prices or dates. Reply with the email body only.',
            "From: {$message['name']} <{$message['email']}>\nSubject: ".($message['subject'] ?? '-')."\n\n{$message['body']}", 1200,
        );
    }

    private function run(string $system, string $user, int $maxTokens): string
    {
        return $this->ai->complete($system, [['role' => 'user', 'content' => $user]], $maxTokens)['text'];
    }

    private function stripFence(string $s): string
    {
        return trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($s)));
    }

}
