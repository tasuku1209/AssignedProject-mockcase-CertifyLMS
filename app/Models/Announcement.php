<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * 一括代入を許可する属性。
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'target_type',
        'target_certification_id',
        'target_user_id',
    ];

    /**
     * 属性のキャスト定義。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => AnnouncementTargetType::class,
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * 配信対象の資格。
     */
    public function targetCertification(): BelongsTo
    {
        return $this->belongsTo(
            Certification::class,
            'target_certification_id',
        );
    }

    /**
     * 配信対象の受講生。
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'target_user_id',
        );
    }

    /**
     * お知らせを配信した管理者。
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }
}
