<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\CreateCheckoutSessionRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingQuota\CreateCheckoutSessionAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生向けの追加面談購入 Controller。
 */
class MeetingQuotaController extends Controller
{
    /**
     * 追加面談パックの購入画面を表示する。
     */
    public function checkout(): View
    {
        $this->authorize('view-meeting-quota-checkout');

        $plans = MeetingPack::query()
            ->published()
            ->ordered()
            ->get();

        return view('meeting-quota.checkout-select', [
            'plans' => $plans,
        ]);
    }

    /**
     * Stripe Checkout を開始する。
     */
    public function store(
        CreateCheckoutSessionRequest $request,
        CreateCheckoutSessionAction $action
    ): RedirectResponse {
        $payment = $action(
            $request->user(),
            $request->validated(),
        );

        return redirect($payment->checkout_url);
    }

    /**
     * Stripe Checkout 完了後の購入完了画面を表示する。
     */
    public function success(): View
    {
        $this->authorize('view-meeting-quota-success');

        return view('meeting-quota.success');
    }
}
