<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAiChatEnabled
{
    /**
     * AIチャット機能が有効な場合のみ後続処理を実行する。
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('ai-chat.enabled')) {
            abort(403, 'AIチャット機能は現在利用できません。');
        }

        return $next($request);
    }
}
