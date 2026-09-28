<?php

declare(strict_types=1);

namespace App\Exceptions\EnrollmentGoal;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 個人目標を達成済みにできない場合の例外(HTTP 409)。
 *
 * 既に達成済みの目標は再度達成済みにできない。
 */
final class EnrollmentGoalAlreadyAchievedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この目標は既に達成済みです。', $previous);
    }
}
