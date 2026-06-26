<?php
// ============================================================
// メール確認リンクの着地点
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_auth_layout.php';

$id    = $_GET['id'] ?? '';
$token = $_GET['token'] ?? '';

$ok = false;
$user = get_user($id);

if ($user && !empty($user['verify_token'])
    && hash_equals($user['verify_token'], (string)$token)) {
    if (!empty($user['email_verified'])) {
        $ok = true;  // 既に確認済み
    } else {
        // メール確認完了：有効化し、トークンを破棄
        update_user($id, [
            'email_verified' => true,
            'verify_token'   => null,
        ]);
        $ok = true;
    }
    // そのままログインさせる
    if ($ok) login_user($id);
}

auth_header('メール確認');
?>
<?php if ($ok): ?>
    <h1>✅ 登録が完了しました</h1>
    <div class="msg success">メールアドレスの確認が完了し、ログインしました。<br>7日間の無料トライアルが始まります。</div>
    <div class="links"><a href="line-stamp-factory_25.php">サービスを開始する →</a></div>
<?php else: ?>
    <h1>確認できませんでした</h1>
    <div class="msg error">リンクが無効か、有効期限が切れています。<br>お手数ですが、もう一度登録をお試しください。</div>
    <div class="links"><a href="signup.php">新規登録へ</a> / <a href="login.php">ログイン</a></div>
<?php endif; ?>
<?php
auth_footer();
