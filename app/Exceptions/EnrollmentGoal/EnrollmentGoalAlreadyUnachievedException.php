<?php

declare(strict_types=1);

namespace App\Exceptions\EnrollmentGoal;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 既に未達の目標に対して達成解除(unmarkAchieved)を呼んだ際の例外(HTTP 409)。
 */
final class EnrollmentGoalAlreadyUnachievedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この目標はまだ達成されていません。', $previous);
    }
}
