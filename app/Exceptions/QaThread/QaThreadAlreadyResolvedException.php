<?php

declare(strict_types=1);

namespace App\Exceptions\QaThread;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * すでに解決済みのスレッドを再度「解決済み」にしようとした際の例外（HTTP 409）。
 */
final class QaThreadAlreadyResolvedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この質問はすでに解決済みです。', $previous);
    }
}
