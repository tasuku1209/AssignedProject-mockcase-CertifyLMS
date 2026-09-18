# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません

## Stripe 決済連携

面談回数追加購入の決済に Stripe Checkout を使用しています。

### 新しく必要になった設定

Stripe のテスト環境を利用するため、`.env` に以下を設定してください。

```dotenv
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

* `STRIPE_SECRET` — Stripe APIへの接続に使用するテスト用Secret Key
* `STRIPE_WEBHOOK_SECRET` — Stripeから送信されるWebhookの署名検証に使用するSigning Secret

`STRIPE_SECRET` は Stripe Dashboard のテスト環境から取得してください。

`STRIPE_WEBHOOK_SECRET` は、Stripe CLIでWebhookの転送を開始した際に表示される `whsec_...` を設定します。

環境変数を設定・変更した場合は、以下を実行してください。

```bash
sail artisan config:clear
```

### Stripeの商品・Price設定

面談回数追加購入では、面談パックごとにStripeのPrice IDを使用します。

デモ環境では、MeetingPackシーダーに設定されているStripe Price IDを使用します。

自分のStripeテスト環境で実際に決済を試す場合、自分のStripeテスト環境でProduct / Priceを作成し、シーダーの `stripe_price_id` を自分のPrice ID（`price_...`）に変更してください。

Price IDは秘密情報ではないため、シーダーに記載してGit管理できます。

### Stripeからの通知受信を実際に試す手順

Stripe CLIを使用して、Stripeから送信されるWebhookをローカルのLaravelアプリケーションへ転送できます。

#### 1. Stripe CLIでログイン

Stripe CLIをインストールした後、以下を実行します。

```bash
stripe login
```

#### 2. Webhookの転送を開始

Laravel Sailを起動した状態で、別ターミナルから以下を実行します。

```bash
export STRIPE_API_KEY="$(grep '^STRIPE_SECRET=' .env | cut -d '=' -f2-)"

stripe listen \
  --api-key "$STRIPE_API_KEY" \
  --events checkout.session.completed \
  --forward-to localhost:8000/webhooks/stripe
```

実行すると、以下のようなWebhook Signing Secretが表示されます。

```text
Ready! Your webhook signing secret is 'whsec_...'
```

表示された `whsec_...` を `.env` の `STRIPE_WEBHOOK_SECRET` に設定します。

```dotenv
STRIPE_WEBHOOK_SECRET=whsec_...
```

設定後、別ターミナルで以下を実行します。

```bash
sail artisan config:clear
```

`stripe listen` は、Webhookのテスト中は起動したままにしてください。

#### 3. Stripe Checkoutで決済する

1. 受講生アカウントでログインします。
2. 面談回数追加購入画面を開きます。
3. 公開済みの面談パックを選択します。
4. Stripe Checkoutへ遷移します。
5. Stripeのテストカードで決済します。

テストカードには以下を使用できます。

```text
カード番号：4242 4242 4242 4242
有効期限：任意の将来日
CVC：任意の3桁
郵便番号：任意
```

#### 4. Webhookの受信を確認する

決済が完了すると、Stripe CLI側に以下のイベントが表示されます。

```text
checkout.session.completed
```

続いて、WebhookがLaravelへ正常に転送されると、以下のような結果が表示されます。

```text
200 POST http://localhost:8000/webhooks/stripe
```

アプリケーション側では、以下を確認します。

* Paymentのステータスが「決済成功」になる
* 購入した面談パックの回数分、面談回数が追加される
* 面談回数の残数に購入分が反映される
* 購入履歴に決済内容が表示される

以上で、Stripe Checkoutでの決済からWebhookの受信、アプリケーション側での決済確定・面談回数追加までを確認できます。

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。
