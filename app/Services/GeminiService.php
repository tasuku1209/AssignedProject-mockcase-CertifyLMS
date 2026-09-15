<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    /**
     * Geminiへテキストを送信する。
     *
     * @return array{
     *     text: string,
     *     model: string,
     *     input_tokens: int|null,
     *     output_tokens: int|null
     * }
     */
    public function generate(string $prompt): array
    {
        $apiKey = config('ai-chat.gemini.api_key');
        $model = config('ai-chat.gemini.model');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Gemini APIキーが設定されていません。');
        }

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('Geminiモデルが設定されていません。');
        }

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
            [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ],
        );

        $response->throw();

        $data = $response->json();

        return [
            'text' => $this->extractText($data),
            'model' => $model,
            'input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? null,
            'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractText(array $data): string
    {
        foreach ($data['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (
                    ($part['text'] ?? null) !== null
                    && is_string($part['text'])
                ) {
                    return $part['text'];
                }
            }
        }

        throw new RuntimeException('Geminiからテキスト回答を取得できませんでした。');
    }
}
