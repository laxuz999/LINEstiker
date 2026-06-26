<?php
// ============================================================
// ログイン
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_auth_layout.php';

auth_session_start();

// すでにログイン済みなら本体へ
if (current_user()) {
    header('Location: line-stamp-factory_25.php');
    exit;
}

$error = '';
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';

    // レート制限（IP+メール単位で連続失敗を抑制）
    $rl_key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? '') . '_' . normalize_email($email_input);

    if (!csrf_check()) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!rate_limit_check($rl_key)) {
        $error = 'ログインの試行回数が上限に達しました。しばらくしてからお試しください。';
    } else {
        $found = find_user_by_email($email_input);
        if (!$found || !password_verify($password, $found['user']['password_hash'] ?? '')) {
            rate_limit_hit($rl_key);
            $error = 'メールアドレスまたはパスワードが正しくありません。';
        } elseif (empty($found['user']['email_verified'])) {
            $error = 'メールアドレスの確認が完了していません。登録時に送信した確認メールをご確認ください。';
        } else {
            login_user($found['id']);
            header('Location: line-stamp-factory_25.php');
            exit;
        }
    }
}

auth_header('ログイン');
?>
<h1>ログイン</h1>
<p class="lead">登録済みのメールアドレスとパスワードでログインしてください。</p>
<?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" action="login.php">
    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    <label for="email">メールアドレス</label>
    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required autocomplete="email">
    <label for="password">パスワード</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
    <button type="submit" class="btn">ログイン</button>
</form>
<div class="links">
    <a href="forgot.php">パスワードをお忘れですか？</a><br>
    アカウントをお持ちでない方は <a href="signup.php">新規登録</a>
</div>
<?php
auth_footer();
