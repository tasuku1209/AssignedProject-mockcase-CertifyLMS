<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\UseCases\Payment\HandleStripeWebhookAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe Webhook を受信する Controller。
 */
class StripeWebhookController extends Controller
{
    /**
     * Stripe からの Webhook を受信する。
     */
    public function handle(
        Request $request,
        HandleStripeWebhookAction $action,
    ): JsonResponse {
        $action->execute(
            $request->getContent(),
            $request->header('Stripe-Signature'),
        );

        return response()->json([
            'received' => true,
        ]);
    }
}
