<?php
// 本番投入後、実行確認でき次第このファイルは削除すること
// ============================================================
// 使い捨て：管理者アカウント作成/更新スクリプト
// GETで ?token=<秘密トークン> を付けて1回だけ叩く。
// ============================================================

require_once __DIR__ . '/auth.php';

// ── 認可（秘密トークン一致チェック）──
define('_BOOT_TOKEN', 'c6a5cbef79aa7b560bd0b9b9eb69e99a');

$given = $_GET['token'] ?? '';
if (!is_string($given) || !hash_equals(_BOOT_TOKEN, $given)) {
    http_response_code(404);
    exit;
}

// ── 作成するアカウント内容（ハードコード）──
$target_email  = 'zz999999999@gmail.com';
$target_pass   = 'yamamot0';

$existing = find_user_by_email($target_email);

if ($existing === null) {
    // 新規作成：create_user()は使わず直接エントリを追加（trial/未確認を避ける）
    $users = load_users();
    $id = bin2hex(random_bytes(16));
    $users[$id] = [
        'email'                    => normalize_email($target_email),
        'password_hash'            => password_hash($target_pass, PASSWORD_DEFAULT),
        'created_at'               => time(),
        'email_verified'           => true,
        'verify_token'             => null,
        'reset_token'              => null,
        'reset_token_expires'      => null,
        'stripe_customer_id'       => null,
        'subscription_status'      => 'active',
        'subscription_period_end' => null,
    ];
    save_users($users);

    echo '作成しました' . "\n";
    echo 'email: ' . htmlspecialchars($target_email, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'user id: ' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\n";
} else {
    // 既存：望む状態に更新
    $id = $existing['id'];
    update_user($id, [
        'email_verified'           => true,
        'subscription_status'      => 'active',
        'subscription_period_end' => null,
        'password_hash'            => password_hash($target_pass, PASSWORD_DEFAULT),
    ]);

    echo '更新しました' . "\n";
    echo 'email: ' . htmlspecialchars($target_email, ENT_QUOTES, 'UTF-8') . "\n";
    echo 'user id: ' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\n";
}
