<?php

namespace App\Services\Audit;

use Exception;
use Illuminate\Support\Facades\Http;

class OpenAiAuditService
{
    public function analyze(array $pageData): array
    {
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) throw new Exception('OPENAI_API_KEY is not set.');

        $model = env('OPENAI_MODEL', 'gpt-4.1-mini');
        $base = rtrim(env('OPENAI_API_BASE', 'https://api.openai.com/v1'), '/');

        $system = $this->systemPrompt();
        $schema = $this->resultSchema();

        // Важно: просим “чистый JSON” и валидируем парсингом
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode([
                    'task' => 'website_audit',
                    'page_data' => $pageData,
                    'output_requirements' => [
                        'return_strict_json' => true,
                        'schema' => $schema,
                        'language' => 'ru'
                    ]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];

        $t0 = microtime(true);
        $res = Http::withToken($apiKey)
            ->timeout(60)
            ->post($base . '/chat/completions', $payload);

        if (!$res->successful()) {
            throw new Exception('OpenAI API error: ' . $res->status() . ' ' . $res->body());
        }

        $text = $res->json('choices.0.message.content');
        if (!is_string($text) || trim($text) === '') {
            throw new Exception('Empty model response.');
        }

        $json = $this->extractJson($text);
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new Exception('Model did not return valid JSON.');
        }

        // минимальная проверка ключей
        foreach (['summary','score','sections','quick_wins'] as $k) {
            if (!array_key_exists($k, $data)) {
                throw new Exception("JSON missing key: {$k}");
            }
        }

        return $data;
    }

    private function extractJson(string $text): string
    {
        $t = trim($text);

        // Если модель вдруг обернула в ```json ... ```
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $t, $m)) {
            return $m[1];
        }

        // Берём первый объект { ... }
        $start = strpos($t, '{');
        $end = strrpos($t, '}');
        if ($start === false || $end === false || $end <= $start) {
            throw new Exception('No JSON object found in model response.');
        }
        return substr($t, $start, $end - $start + 1);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Ты — профессиональный аудитор сайтов. Тебе дают уже извлечённые данные страницы (title, meta, h1/h2, текст, ссылки, изображения).
НЕЛЬЗЯ придумывать факты, которых нет во входных данных.
Нужно выдать строгий JSON по заданной схеме, на русском языке.

Проведи аудит по направлениям:
1) SEO
2) Контент (понятность, структура, польза)
3) Юзабилити (UX)
4) Безопасность (только по косвенным признакам: https/robots/meta и т.п., без сканирования)
5) Маркетинг/продающая часть (оффер, CTA, доверие, возражения)

Оцени по 100-балльной шкале и предложи конкретные улучшения.
PROMPT;
    }

    private function resultSchema(): array
    {
        // Это “описание схемы” для модели (не JSON Schema стандарт),
        // но достаточно, чтобы держать структуру стабильно.
        return [
            'summary' => 'string (2-4 предложения)',
            'score' => 'integer 0..100',
            'sections' => [
                'seo' => [
                    'score' => '0..100',
                    'issues' => 'array of strings',
                    'recommendations' => 'array of strings',
                    'notes' => 'array of strings'
                ],
                'content' => [
                    'score' => '0..100',
                    'what_is_this_site' => 'string (1-2 предложения)',
                    'issues' => 'array of strings',
                    'recommendations' => 'array of strings'
                ],
                'ux' => [
                    'score' => '0..100',
                    'issues' => 'array of strings',
                    'recommendations' => 'array of strings'
                ],
                'security' => [
                    'score' => '0..100',
                    'notes' => 'array of strings (без сканирования)',
                    'recommendations' => 'array of strings'
                ],
                'marketing' => [
                    'score' => '0..100',
                    'issues' => 'array of strings',
                    'recommendations' => 'array of strings',
                    'cta_ideas' => 'array of strings'
                ],
            ],
            'quick_wins' => 'array of strings (5-10 пунктов)',
            'risks_and_limits' => 'array of strings (что нельзя уверенно сказать по данным)'
        ];
    }
}
