<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 質問スレッドの解決状態を表す Enum。
 *
 * - Unresolved: 未解決
 * - Resolved: 解決済
 */
enum QaThreadStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Unresolved => '未解決',
            self::Resolved => '解決済',
        };
    }
}
