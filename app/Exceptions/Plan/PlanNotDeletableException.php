<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない受講プランを削除しようとした際の例外（HTTP 409）。
 *
 * - 下書き状態のプランのみ削除可能
 * - 受講者が紐づいているプランは削除不可
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            '下書き状態かつ受講者が紐づいていないプランのみ削除できます。',
            $previous
        );
    }
}
