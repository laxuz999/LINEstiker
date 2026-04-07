<?php
// ============================================================
// マイページ / Stripe カスタマーポータル
// 支払い済みユーザーをStripeのポータルへリダイレクトします
// ポータルでサブスクリプションの確認・解約・支払い方法変更が可能
// ============================================================
require_once __DIR__ . '/stripe_config.php';

$data_dir   = __DIR__ . '/data';
$token_file = $data_dir . '/paid_tokens.json';

// paid_token クッキーを確認
$paid_token = $_COOKIE['paid_token'] ?? '';
if (!$paid_token) {
    // 未ログイン → トップへリダイレクト
    header('Location: /Linestiker/');
    exit;
}

$tokens     = file_exists($token_file) ? (json_decode(file_get_contents($token_file), true) ?: []) : [];
$token_data = $tokens[$paid_token] ?? null;

// トークンが無効
if (!$token_data) {
    header('Location: /Linestiker/');
    exit;
}

$customer_id = $token_data['customer_id'] ?? null;
$mode        = $token_data['mode'] ?? 'live';
$key         = ($mode === 'test') ? STRIPE_SECRET_KEY_TEST : STRIPE_SECRET_KEY_LIVE;
$return_url  = 'https://laxuz.net/Linestiker/';

// customer_id が保存されていない場合はメール案内
if (!$customer_id) {
    showPortalError('お客様情報が見つかりません。恐れ入りますがメールにてご連絡ください。');
}

// Stripe Customer Portal セッションを作成
$ch = curl_init('https://api.stripe.com/v1/billing_portal/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'customer'   => $customer_id,
        'return_url' => $return_url,
    ]),
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key],
    CURLOPT_TIMEOUT    => 10,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($http_code === 200 && isset($data['url'])) {
    // ポータルへリダイレクト
    header('Location: ' . $data['url']);
    exit;
}

// ポータルセッション作成失敗時
showPortalError('マイページへの接続に失敗しました。時間をおいて再度お試しいただくか、メールにてご連絡ください。');

// ============================================================
// エラー表示関数
// ============================================================
function showPortalError($msg) {
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>マイページ - LINEスタンププロンプトファクトリー</title>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
        <style>
            *{box-sizing:border-box;margin:0;padding:0;}
            body{font-family:"Noto Sans JP",sans-serif;background:#f5f0e8;display:flex;flex-direction:column;min-height:100vh;}
            .top-bar{background:#06C755;border-bottom:4px solid #00B900;padding:14px 24px;display:flex;align-items:center;gap:12px;}
            .top-bar .icon{font-size:22px;}
            .top-bar .title{font-size:18px;font-weight:700;color:#fff;}
            .wrap{flex:1;display:flex;justify-content:center;align-items:center;padding:40px 24px;}
            .card{background:#fff;border:1.5px solid #c8bfaa;max-width:480px;width:100%;padding:40px 32px;text-align:center;}
            h2{font-size:20px;font-weight:700;color:#1a1a1a;margin-bottom:16px;}
            p{font-size:14px;color:#555;line-height:1.8;margin-bottom:20px;}
            .btn{display:inline-block;background:#06C755;color:#fff;padding:12px 28px;font-size:14px;font-weight:700;text-decoration:none;}
            .btn:hover{background:#00B900;}
            .mail-link{color:#06C755;font-weight:700;}
        </style>
    </head>
    <body>
        <div class="top-bar">
            <div class="icon">🏭</div>
            <div class="title">LINEスタンププロンプトファクトリー</div>
        </div>
        <div class="wrap">
            <div class="card">
                <h2>マイページ</h2>
                <p><?php echo htmlspecialchars($msg); ?></p>
                <p>お問い合わせ：<a href="mailto:info@laxuz.net" class="mail-link">info@laxuz.net</a></p>
                <a href="/Linestiker/" class="btn">← サービスに戻る</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
