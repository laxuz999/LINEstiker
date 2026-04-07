<?php
// ============================================================
// Stripe Webhook エンドポイント
// ============================================================
// Stripeダッシュボードで以下を設定してください：
//   Webhook URL: https://laxuz.net/Linestiker/webhook.php
//   イベント: checkout.session.completed
//
// 設定後、「署名シークレット（whsec_xxxxx）」を下記に貼り付けてください。
// ============================================================

// テストモード用シークレット（brilliant-spark）
$webhook_secret_test = 'whsec_N6hfS5FroJNCLygUA4LyZiOMWPwJmXtQ';
// 本番モード用シークレット（engaging-victory）
$webhook_secret_live = 'whsec_8kCIyiZ9yRhPJudHruVDEIVvTCHTq7BN';

$data_dir  = __DIR__ . '/data';
$token_file = $data_dir . '/paid_tokens.json';

// ── トークンファイル読み書き ──
function load_tokens($f) {
    if (!file_exists($f)) return [];
    return json_decode(file_get_contents($f), true) ?: [];
}
function save_tokens($f, $tokens) {
    file_put_contents($f, json_encode($tokens));
}

// ── リクエスト受信 ──
$payload   = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── 署名検証 ──
function verify_stripe_signature($payload, $sig_header, $secret) {
    if (!$sig_header) return false;
    $parts = [];
    foreach (explode(',', $sig_header) as $part) {
        [$k, $v] = explode('=', $part, 2);
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];
    if (!$timestamp || !$signatures) return false;

    // タイムスタンプが5分以上古い場合は拒否（リプレイ攻撃防止）
    if (abs(time() - (int)$timestamp) > 300) return false;

    $signed_payload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed_payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

$verified = verify_stripe_signature($payload, $sig_header, $webhook_secret_test)
         || verify_stripe_signature($payload, $sig_header, $webhook_secret_live);
if (!$verified) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ── イベント処理 ──
$event = json_decode($payload, true);
$type  = $event['type'] ?? '';

if ($type === 'checkout.session.completed') {
    $session    = $event['data']['object'];
    $session_id = $session['id'] ?? '';

    if ($session_id && strpos($session_id, 'cs_') === 0) {
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
        $tokens = load_tokens($token_file);

        // 既に同じsession_idで登録済みなら重複しない
        $already = false;
        foreach ($tokens as $token => $info) {
            if (($info['session_id'] ?? '') === $session_id) {
                $already = true;
                break;
            }
        }

        if (!$already) {
            $new_token = bin2hex(random_bytes(32));
            $tokens[$new_token] = [
                'created_at'  => time(),
                'session_id'  => $session_id,
                'customer_id' => $session['customer'] ?? null,
                'email'       => $session['customer_details']['email'] ?? '',
                'source'      => 'webhook',
                'mode'        => (strpos($session_id, 'cs_test_') === 0) ? 'test' : 'live',
            ];
            save_tokens($token_file, $tokens);
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
