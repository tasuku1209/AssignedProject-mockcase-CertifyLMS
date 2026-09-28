<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChatMessage\StoreRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChatMessage\StoreAction;
use Illuminate\Http\JsonResponse;

/**
 * AIチャットメッセージ Controller。
 *
 * AI相談へのメッセージ送信を提供する。
 */
class AiChatMessageController extends Controller
{
    public function store(
        AiChatConversation $conversation,
        StoreRequest $request,
        StoreAction $action,
    ): JsonResponse {
        $result = $action(
            $conversation,
            $request->user(),
            $request->validated(),
        );

        return response()->json($result);
    }
}
