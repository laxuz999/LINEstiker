# LINEスタンププロンプトファクトリー — プロジェクトメモ

## プロジェクト概要
LINEスタンプ用のAIプロンプトを一括生成するWebツール。
キャラクター設定・ビジュアルスタイル・セリフを選ぶだけで、40個分のプロンプトを生成できる。

## ファイル構成

```
/Users/blackcoffee/my-project/LINEstiker/
├── line-stamp-factory_25.php   # メインファイル（PHP版・Stripe連携あり）
├── line-stamp-factory_25.html  # 静的HTML版（Stripe・試用期間なし）
├── index.php                   # クリーンURL用（line-stamp-factory_25.phpをrequire）
├── data/
│   ├── .htaccess               # 直接アクセス拒否
│   └── paid_tokens.json        # 支払い済みトークン保存（Stripe決済後に自動生成）
└── test.php                    # PHPテスト用
```

## サーバー情報（ConoHa WING）

| 項目 | 値 |
|------|-----|
| FTPサーバー | www244.conoha.ne.jp |
| FTPユーザー名 | ftpadmin@laxuz.net |
| FTPパスワード | $psadmin001 |
| サーバールート | /home/c6589754/ |
| 公開ディレクトリ | /home/c6589754/public_html/laxuz.net/Linestiker/ |
| 本番URL | https://laxuz.net/Linestiker/ |

### FTPアップロードコマンド例
```bash
BASE="ftp://www244.conoha.ne.jp/public_html/laxuz.net/Linestiker"
USER='ftpadmin@laxuz.net:$psadmin001'

curl -T line-stamp-factory_25.php --user "$USER" "$BASE/line-stamp-factory_25.php"
curl -T index.php                 --user "$USER" "$BASE/index.php"
curl -T data/.htaccess            --user "$USER" "$BASE/data/.htaccess" --ftp-create-dirs
```

## GitHub

- リポジトリ: https://github.com/laxuz999/LINEstiker
- ブランチ: main

## Stripe設定

- 決済リンク: https://buy.stripe.com/3cI4gy9wQ0qg9kU5ACdby00
- 月額: ¥1,000
- 成功リダイレクト: `?session_id={CHECKOUT_SESSION_ID}` をURLに付与してリダイレクト

## セキュリティ実装

### 支払い認証（サーバーサイドトークン）
- Stripeから `?session_id=cs_xxxxx` でリダイレクト → PHPが `cs_` プレフィックスを確認
- `bin2hex(random_bytes(32))` でトークン生成 → `data/paid_tokens.json` に保存
- Cookieに `paid_token` をセット（Secure・HttpOnly）
- 以降のアクセスでCookieのトークンをJSONと照合 → 一致すれば有料ユーザー

### 無料体験（7日間）
- 初回アクセス時に `trial_start_date` Cookieをセット
- JSでも `lsf_trial_start` をlocalStorageに保存（Cookie削除対策）
- 7日経過後かつ未払いの場合 → 期限切れページを表示

## localStorage キー一覧

| キー | 内容 |
|------|------|
| `customTexts` | カスタム追加セリフ（最大48個） |
| `lsf_charDesc` | キャラクター説明文 |
| `lsf_style` | 選択中のビジュアルスタイル |
| `lsf_trial_start` | トライアル開始日（Unixタイム） |

## 主要機能

- **厳選セリフ**: SERIF_POOL（160件・4ページ×40件）を「入替」ボタンでサイクル
- **カスタム入力**: 追加セリフを最大48個まで保存（localStorage永続化）
- **全体リセット**: セリフ・スタイルをリセット（カスタム入力は保持）
- **ビジュアルスタイル**: 20種以上のスタイルをカテゴリ別タブで選択

## デザイン

- メインカラー: `#06C755`（LINE グリーン）
- ダークグリーン: `#00B900`
- フォント: Noto Sans JP / IBM Plex Mono

## 過去のバグ修正（再発注意）

1. **TDZ（Temporal Dead Zone）クラッシュ**: `let` 変数の宣言より前で使用するとReferenceErrorで全JSがクラッシュ → イベントリスナーが一切登録されずボタン全滅
2. **null参照クラッシュ**: `document.getElementById()` がnullを返す要素に `.innerHTML=''` → TypeError → 同様にJS全滅。`if(!c)return;` のnullガードを必ず入れること
