<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 決済テーブル(payments)が存在しない環境では FK 追加をスキップする。
        // 決済 Feature が後続で導入された後に migrate:fresh を走らせれば、
        // payments テーブル作成後にこの Migration が走り FK 制約を追加できる。
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->foreign('related_payment_id')
                ->references('id')
                ->on('payments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->dropForeign(['related_payment_id']);
        });
    }
};
