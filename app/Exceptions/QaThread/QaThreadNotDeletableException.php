<?php

declare(strict_types=1);

namespace App\Exceptions\QaThread;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない質問スレッドを削除しようとした際の例外（HTTP 409）。
 *
 * QaThread\DestroyAction が削除条件に違反した場合に throw する。
 */
final class QaThreadNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この質問は削除できません。', $previous);
    }
}
