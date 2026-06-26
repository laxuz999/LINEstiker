# ログイン制導入 計画書

## 0. 背景（なぜ必要か）

現状の課金判定はすべて**クッキー依存**：

- `trial_start_date` クッキー → 無料7日間の起点
- `paid_token` クッキー → 支払い済みフラグ

問題：シークレットウィンドウ／クッキー削除／別ブラウザ／別端末では
クッキーが無い → トライアルが何度でも再生成され、**実質永久無料**。

さらにツール本体はクライアント側JavaScriptのため、トライアル中に
「ページ保存」「ソース表示」で中身を丸ごとコピー可能。

→ クッキーゲートの改良では塞がらない。**アクセスをアカウントに紐付ける**しかない。

---

## 1. ゴール

- 利用にはログインを必須にする（＝シークレットでも突破不可）
- 無料トライアルは「アカウント単位で1回・7日」
- 課金状態はアカウントに紐付け、解約・支払い失敗も正しく反映
- Stripe決済とアカウントを確実に連携（誰が払ったか追跡可能に）

※注意：ツール本体がクライアントJSである以上、ログインしても
「ログイン済みユーザーがページ保存」までは防げない。
それを防ぐにはロジックのサーバー側移設（別フェーズ）が必要。本計画はアクセス制御まで。

---

## 2. 構成（共有ホスティング / PHP / FTP デプロイ前提）

DBは導入せず、現状踏襲で**JSONファイル + PHPセッション**で実装する。
（将来規模が増えたらSQLiteへ移行可能な設計にする）

### 新規データ
- `data/users.json` … アカウント情報
  ```json
  {
    "<user_id>": {
      "email": "a@example.com",
      "password_hash": "$2y$...",     // password_hash() で生成
      "created_at": 1700000000,        // トライアル起点（アカウント単位）
      "stripe_customer_id": null,
      "subscription_status": "trial",  // trial | active | canceled | past_due
      "subscription_current_period_end": null
    }
  }
  ```
- email → user_id の逆引き用に、保存時にメールを正規化（小文字）してユニーク制約。

### 新規ページ
| ファイル | 役割 |
|---|---|
| `signup.php` | 会員登録（メール+パスワード）。バリデーション→`users.json`に追加→自動ログイン |
| `login.php`  | ログイン。`password_verify()`→PHPセッション確立 |
| `logout.php` | セッション破棄 |
| `auth.php`   | 共通：`session_start()`、現在ユーザー取得、課金状態判定の関数群 |

### 既存ページの改修
| ファイル | 変更 |
|---|---|
| `line-stamp-factory_25.php` | 冒頭でログイン必須化。クッキー判定を**セッション+アカウント**判定に置換。未ログインは`login.php`へ。トライアル残日数は`users.json`の`created_at`から計算 |
| `webhook.php` | `checkout.session.completed`で`client_reference_id`(=user_id)を読み、該当アカウントを`active`化・`stripe_customer_id`保存。加えて`customer.subscription.updated/deleted`で解約・期限切れを反映 |
| `portal.php` | クッキーではなくログイン中アカウントの`stripe_customer_id`からポータル生成 |
| 決済導線（買うボタン） | Payment Link に `?client_reference_id=<user_id>&prefilled_email=<email>` を付与し、誰の決済か追跡可能に |

---

## 3. 課金フロー（連携の肝）

1. ユーザー登録/ログイン → `user_id` 確定
2. 「登録する」ボタン → Stripe Payment Link に
   `client_reference_id=<user_id>` と `prefilled_email` を付けて遷移
3. 決済完了 → Stripe Webhook が `checkout.session.completed` を送信
4. `webhook.php` が `client_reference_id` から user_id を特定し、
   そのアカウントを `subscription_status=active`、`stripe_customer_id` 保存
5. 以降、ページ表示時に `subscription_status` を見て解放/ブロック判定
6. 解約・支払い失敗は `customer.subscription.deleted/updated` Webhookで
   `canceled`/`past_due` に更新 → 自動でアクセス制御に反映

> 重要：現状の `session_id` をURLで受けてトークン発行する仕組みは廃止し、
> **Webhookを唯一の正とする**（URL偽造・クライアント側判定を排除）。

---

## 4. セキュリティ要点

- パスワードは `password_hash(PASSWORD_DEFAULT)` / `password_verify()`
- セッションは `session_set_cookie_params` で `Secure`+`HttpOnly`+`SameSite=Lax`
- `users.json` は `.htaccess` で直アクセス禁止（`stripe_config.php`と同様）
- JSON書き込みは `flock()` で排他制御（同時登録の競合防止）
- ログイン試行のレート制限（簡易：IP+メール単位で連続失敗をカウント）
- メール重複登録の防止、メール形式バリデーション

---

## 5. 作業順序（小さい単位・1変更1コミット）

1. `mailer.php`：メール送信共通関数（`mail()`ラッパー）
2. `auth.php`：セッション基盤＋users.json読み書き＋課金/状態判定関数
3. `signup.php`：会員登録（pending保存＋確認メール送信）
4. `verify.php`：メール確認リンク着地（status=active化）
5. `login.php` / `logout.php`：ログイン/ログアウト
6. `forgot.php` / `reset.php`：パスワードリセット
7. `users.json` の `.htaccess` 保護
8. `line-stamp-factory_25.php`：ログイン必須化・トライアル判定をアカウント基準へ移植、旧クッキー判定削除
9. `webhook.php`：`client_reference_id`連携＋subscriptionイベント対応
10. 決済ボタンに `client_reference_id`/`prefilled_email` 付与
11. `portal.php`：ログイン中アカウント基準に変更
12. テスト（後述）

---

## 6. テスト項目

- [ ] 未ログインでツールにアクセス → `login.php`へリダイレクト
- [ ] シークレットウィンドウでアクセス → ログインしないと使えない（＝旧バグ解消）
- [ ] 新規登録 → 確認メール受信 → リンククリックでアカウント有効化
- [ ] 確認前（pending）のアカウントはログイン/利用不可
- [ ] 確認後ログイン → 7日トライアル開始 → 残日数表示
- [ ] 同一メール再登録不可
- [ ] パスワードを忘れる → `forgot.php`申請 → メールのリンク → `reset.php`で再設定 → 新パスワードでログイン可
- [ ] リセットトークンの有効期限切れ後はリンク無効
- [ ] トライアル期限切れアカウントでログイン → ブロック画面
- [ ] テスト決済 → Webhook → 該当アカウントが `active` → 解放
- [ ] マイページ（ポータル）で解約 → Webhook → 次回判定でブロック
- [ ] `users.json` / `stripe_config.php` への直アクセスが403

---

## 7. 確定した仕様

- **パスワードリセット：入れる**（初回リリースに含める）
- **メール認証（登録時の本人確認メール）：入れる**
  - 登録時は `pending`（仮登録）状態で保存し、確認リンクのクリックで `active` 化
  - 確認前のアカウントはログイン/利用不可
- **メール送信方法：PHP `mail()`** で実装
  - 送信元（From）は `noreply@laxuz.net` 等を使用
  - 件名・本文はUTF-8、`mb_encode_mimeheader` でヘッダーエンコード
  - 万一届かない場合は外部SMTP/APIへ差し替え可能な関数構造にしておく
- **既存ユーザー移行：不要**（`paid_tokens.json` からの自動移行は作らない）

### メール認証の追加データ（users.json）
```json
{
  "status": "pending",              // pending | active（メール確認済み）
  "verify_token": "<ランダム>",      // 確認リンク用。確認後は破棄
  "reset_token": null,              // パスワードリセット用一時トークン
  "reset_token_expires": null       // リセットトークンの有効期限
}
```

### 追加ページ
| ファイル | 役割 |
|---|---|
| `verify.php` | メール確認リンクの着地点。`verify_token`照合→`status=active` |
| `forgot.php` | パスワード再設定の申請（メール入力→リセットリンク送信） |
| `reset.php`  | リセットリンクの着地点。`reset_token`照合→新パスワード設定 |
| `mailer.php` | メール送信の共通関数（`mail()`ラッパー。後で差し替え可能に） |

---

## 8. 工数感

- 認証3ページ + auth基盤：中
- 既存3ファイル改修：中
- Webhook拡張（subscriptionイベント）：小〜中
- テスト：小
合計：**1〜2日規模**（メール認証/パスワードリセットを除く）
