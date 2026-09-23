<?php

declare(strict_types=1);

namespace App\Exceptions\Setting;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * アバター画像のストレージ操作（保存 / 削除）が失敗した場合に throw される。
 *
 * DB の更新と Storage の操作を組み合わせ、
 * DB と Storage の不整合を防ぐために使用する。
 */
final class AvatarStorageException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            500,
            'アバター画像の保存に失敗しました。時間をおいて再度お試しください。',
            $previous,
        );
    }
}
