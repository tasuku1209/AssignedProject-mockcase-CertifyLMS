<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_returns_answer_title_and_usage_metadata(): void
    {
        Config::set('ai-chat.gemini.api_key', 'test-api-key');
        Config::set('ai-chat.gemini.model', 'gemini-3.1-flash-lite');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => "TITLE: Laravelの学習について\nANSWER:\nLaravelはPHPのWebアプリケーションフレームワークです。",
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 25,
                    'candidatesTokenCount' => 40,
                ],
            ]),
        ]);

        $result = app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );

        $this->assertSame(
            'LaravelはPHPのWebアプリケーションフレームワークです。',
            $result['text'],
        );
        $this->assertSame(
            'Laravelの学習について',
            $result['title'],
        );
        $this->assertSame(
            'gemini-3.1-flash-lite',
            $result['model'],
        );
        $this->assertSame(25, $result['input_tokens']);
        $this->assertSame(40, $result['output_tokens']);

        Http::assertSent(function ($request): bool {
            return $request->url()
                === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent'
                && $request->header('x-goog-api-key')[0] === 'test-api-key'
                && $request->header('Content-Type')[0] === 'application/json'
                && $request->data()['contents'][0]['role'] === 'user'
                && $request->data()['contents'][0]['parts'][0]['text']
                === 'Laravelについて教えてください。';
        });
    }

    public function test_generate_throws_exception_when_api_key_is_missing(): void
    {
        Config::set('ai-chat.gemini.api_key', '');

        Config::set(
            'ai-chat.gemini.model',
            'gemini-3.1-flash-lite',
        );

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini APIキーが設定されていません。',
        );

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );

        Http::assertNothingSent();
    }

    public function test_generate_throws_exception_when_model_is_missing(): void
    {
        Config::set(
            'ai-chat.gemini.api_key',
            'test-api-key',
        );

        Config::set('ai-chat.gemini.model', '');

        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Geminiモデルが設定されていません。',
        );

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );

        Http::assertNothingSent();
    }

    public function test_generate_throws_exception_when_gemini_returns_no_text(): void
    {
        Config::set(
            'ai-chat.gemini.api_key',
            'test-api-key',
        );

        Config::set(
            'ai-chat.gemini.model',
            'gemini-3.1-flash-lite',
        );

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Geminiからテキスト回答を取得できませんでした。',
        );

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );
    }

    public function test_generate_throws_exception_when_title_cannot_be_extracted(): void
    {
        Config::set(
            'ai-chat.gemini.api_key',
            'test-api-key',
        );

        Config::set(
            'ai-chat.gemini.model',
            'gemini-3.1-flash-lite',
        );

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'ANSWER: Laravelについての回答です。',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Geminiからタイトルを取得できませんでした。',
        );

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );
    }

    public function test_generate_throws_exception_when_answer_cannot_be_extracted(): void
    {
        Config::set(
            'ai-chat.gemini.api_key',
            'test-api-key',
        );

        Config::set(
            'ai-chat.gemini.model',
            'gemini-3.1-flash-lite',
        );

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'TITLE: Laravelの学習について',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Geminiから回答を取得できませんでした。',
        );

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );
    }

    public function test_generate_throws_exception_when_gemini_returns_error_response(): void
    {
        Config::set(
            'ai-chat.gemini.api_key',
            'test-api-key',
        );

        Config::set(
            'ai-chat.gemini.model',
            'gemini-3.1-flash-lite',
        );

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'Invalid request.',
                ],
            ], 400),
        ]);

        $this->expectException(RequestException::class);

        app(GeminiService::class)->generate(
            'Laravelについて教えてください。',
        );
    }
}
