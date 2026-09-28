<?php

declare(strict_types=1);

namespace App\Exceptions\GoogleCalendar;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Google Calendar OAuth の state 検証失敗を表す例外。
 *
 * OAuth を開始したユーザーと callback のユーザーが一致しない、
 * または state が一致しない場合に発生する。
 */
final class InvalidOAuthStateException extends HttpException
{
    public function __construct(
        string $message = 'Google Calendarの認証情報が不正です。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(403, $message, $previous);
    }
}
