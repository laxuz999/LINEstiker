<?php
// ============================================================
// パスワード再設定（リンク着地）
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_auth_layout.php';

auth_session_start();

$id    = $_GET['id'] ?? ($_POST['id'] ?? '');
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

// トークンの有効性を検証
function reset_token_valid($user, $token) {
    if (!$user) return false;
    if (empty($user['reset_token'])) return false;
    if (empty($user['reset_token_expires']) || time() > $user['reset_token_expires']) return false;
    return hash_equals($user['reset_token'], (string)$token);
}

$user  = get_user($id);
$valid = reset_token_valid($user, $token);
$error = '';
$done  = false;

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!csrf_check()) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } elseif ($password !== $password2) {
        $error = '確認用パスワードが一致しません。';
    } else {
        // 新パスワード設定＋トークン破棄。メール確認も済みとみなす。
        update_user($id, [
            'password_hash'       => password_hash($password, PASSWORD_DEFAULT),
            'reset_token'         => null,
            'reset_token_expires' => null,
            'email_verified'      => true,
        ]);
        $done = true;
    }
}

auth_header('パスワード再設定');
?>
<?php if ($done): ?>
    <h1>✅ 変更が完了しました</h1>
    <div class="msg success">新しいパスワードを設定しました。<br>新しいパスワードでログインしてください。</div>
    <div class="links"><a href="login.php">ログイン画面へ</a></div>
<?php elseif (!$valid): ?>
    <h1>リンクが無効です</h1>
    <div class="msg error">このリンクは無効か、有効期限（1時間）が切れています。<br>お手数ですが、もう一度お申し込みください。</div>
    <div class="links"><a href="forgot.php">再設定をやり直す</a></div>
<?php else: ?>
    <h1>新しいパスワードを設定</h1>
    <p class="lead">新しいパスワードを入力してください。</p>
    <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="reset.php">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label for="password">新しいパスワード</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <div class="hint">8文字以上</div>
        <label for="password2">新しいパスワード（確認）</label>
        <input type="password" id="password2" name="password2" required autocomplete="new-password">
        <button type="submit" class="btn">パスワードを変更</button>
    </form>
<?php endif; ?>
<?php
auth_footer();
