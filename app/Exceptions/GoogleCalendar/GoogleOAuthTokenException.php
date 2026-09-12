<?php

declare(strict_types=1);

namespace App\Exceptions\GoogleCalendar;

use RuntimeException;

/**
 * Google OAuth のアクセストークン取得失敗を表す例外。
 */
final class GoogleOAuthTokenException extends RuntimeException
{
    public function __construct(
        string $message = 'Google OAuth token の取得に失敗しました。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
