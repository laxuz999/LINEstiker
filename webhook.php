<?php
// ============================================================
// Stripe Webhook エンドポイント（アカウント連携版）
// ============================================================
// Stripeダッシュボードで以下のイベントを送信する設定にしてください：
//   Webhook URL: https://laxuz.xyz/LINEstiker/webhook.php
//   イベント:
//     - checkout.session.completed        … 決済完了→アカウントをactive化
//     - customer.subscription.updated      … 解約予約・支払い状態の変化を反映
//     - customer.subscription.deleted      … サブスク終了→アカウントをcanceled化
//
// 署名シークレット（whsec_xxxxx）は下記に設定済み。
// ============================================================

require_once __DIR__ . '/auth.php';

// テストモード用シークレット（brilliant-spark）
$webhook_secret_test = 'whsec_N6hfS5FroJNCLygUA4LyZiOMWPwJmXtQ';
// 本番モード用シークレット（engaging-victory）
$webhook_secret_live = 'whsec_8kCIyiZ9yRhPJudHruVDEIVvTCHTq7BN';

// ── リクエスト受信 ──
$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── 署名検証 ──
function verify_stripe_signature($payload, $sig_header, $secret) {
    if (!$sig_header) return false;
    $parts = [];
    foreach (explode(',', $sig_header) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        $parts[$k][] = $v;
    }
    $timestamp  = $parts['t'][0] ?? null;
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
$obj   = $event['data']['object'] ?? [];

switch ($type) {

    // 決済完了 → 該当アカウントを active 化
    case 'checkout.session.completed':
        $user_id     = $obj['client_reference_id'] ?? null;
        $customer_id = $obj['customer'] ?? null;

        // client_reference_id があれば本人を直接特定（最優先）
        $target_id = null;
        if ($user_id && get_user($user_id)) {
            $target_id = $user_id;
        } elseif ($customer_id) {
            // 念のため customer_id でも照合
            $found = find_user_by_stripe_customer($customer_id);
            if ($found) $target_id = $found['id'];
        }

        if ($target_id) {
            update_user($target_id, [
                'subscription_status' => 'active',
                'stripe_customer_id'  => $customer_id,
                'mode'                => !empty($event['livemode']) ? 'live' : 'test',
            ]);
        }
        break;

    // サブスク更新 → ステータスを反映（active / past_due / canceled 等）
    case 'customer.subscription.updated':
        $customer_id = $obj['customer'] ?? null;
        $found = find_user_by_stripe_customer($customer_id);
        if ($found) {
            $stripe_status = $obj['status'] ?? '';
            // Stripeのステータスを当システムの区分にマッピング
            if (in_array($stripe_status, ['active', 'trialing'], true)) {
                $status = 'active';
            } elseif (in_array($stripe_status, ['past_due', 'unpaid', 'incomplete'], true)) {
                $status = 'past_due';
            } elseif (in_array($stripe_status, ['canceled', 'incomplete_expired'], true)) {
                $status = 'canceled';
            } else {
                $status = $found['user']['subscription_status'] ?? 'canceled';
            }
            update_user($found['id'], [
                'subscription_status'     => $status,
                'subscription_period_end' => $obj['current_period_end'] ?? null,
            ]);
        }
        break;

    // サブスク終了 → canceled
    case 'customer.subscription.deleted':
        $customer_id = $obj['customer'] ?? null;
        $found = find_user_by_stripe_customer($customer_id);
        if ($found) {
            update_user($found['id'], ['subscription_status' => 'canceled']);
        }
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
