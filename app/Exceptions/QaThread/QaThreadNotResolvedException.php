<?php

declare(strict_types=1);

namespace App\Exceptions\QaThread;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 解決済みではないスレッドを「未解決に戻す」操作を行った際の例外（HTTP 409）。
 */
final class QaThreadNotResolvedException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('この質問は解決済みではありません。', $previous);
    }
}
