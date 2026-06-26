<?php
// ============================================================
// 会員登録（メール認証あり）
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
$done  = false;
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');
    $password    = $_POST['password'] ?? '';
    $password2   = $_POST['password2'] ?? '';

    if (!csrf_check()) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error = 'メールアドレスの形式が正しくありません。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } elseif ($password !== $password2) {
        $error = '確認用パスワードが一致しません。';
    } elseif (is_email_taken($email_input)) {
        $error = 'このメールアドレスは既に登録されています。';
    } else {
        // アカウント作成（pending）→ 確認メール送信
        $res = create_user($email_input, $password);
        $verify_url = SITE_BASE_URL . 'verify.php?id=' . urlencode($res['id'])
                    . '&token=' . urlencode($res['verify_token']);
        $body = "LINEスタンププロンプトファクトリーにご登録いただきありがとうございます。\n\n"
              . "下記のリンクをクリックして、メールアドレスの確認を完了してください。\n\n"
              . $verify_url . "\n\n"
              . "※このリンクに心当たりがない場合は、このメールを破棄してください。\n";
        send_mail($res['user']['email'], '【メール確認】LINEスタンププロンプトファクトリー', $body);
        $done = true;
    }
}

auth_header('新規登録');
?>
<?php if ($done): ?>
    <h1>確認メールを送信しました</h1>
    <div class="msg success">
        <strong><?php echo htmlspecialchars($email_input); ?></strong> 宛に確認メールをお送りしました。<br>
        メール内のリンクをクリックすると登録が完了します。
    </div>
    <p class="lead">メールが届かない場合は、迷惑メールフォルダもご確認ください。</p>
    <div class="links"><a href="login.php">ログイン画面へ</a></div>
<?php else: ?>
    <h1>新規登録</h1>
    <p class="lead">メールアドレスとパスワードで登録します。<br>登録後、7日間の無料トライアルをご利用いただけます。</p>
    <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="signup.php">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required autocomplete="email">
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <div class="hint">8文字以上</div>
        <label for="password2">パスワード（確認）</label>
        <input type="password" id="password2" name="password2" required autocomplete="new-password">
        <button type="submit" class="btn">登録する</button>
    </form>
    <div class="links">すでにアカウントをお持ちの方は <a href="login.php">ログイン</a></div>
<?php endif; ?>
<?php
auth_footer();
