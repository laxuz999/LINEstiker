<?php
// ============================================================
// パスワードリセット申請
// ============================================================
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_auth_layout.php';

auth_session_start();

$error = '';
$done  = false;
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_input = trim($_POST['email'] ?? '');

    if (!csrf_check()) {
        $error = 'セッションの有効期限が切れました。もう一度お試しください。';
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $error = 'メールアドレスの形式が正しくありません。';
    } else {
        // アカウントが存在する場合のみ送信。ただし結果は常に同じ表示にして
        // 「そのメールが登録済みか」を外部に漏らさない（列挙攻撃対策）。
        $found = find_user_by_email($email_input);
        if ($found) {
            $reset_token = bin2hex(random_bytes(32));
            update_user($found['id'], [
                'reset_token'         => $reset_token,
                'reset_token_expires' => time() + 3600,  // 1時間有効
            ]);
            $reset_url = SITE_BASE_URL . 'reset.php?id=' . urlencode($found['id'])
                       . '&token=' . urlencode($reset_token);
            $body = "パスワード再設定のご依頼を受け付けました。\n\n"
                  . "下記のリンクから新しいパスワードを設定してください（1時間有効）。\n\n"
                  . $reset_url . "\n\n"
                  . "※心当たりがない場合は、このメールを破棄してください。パスワードは変更されません。\n";
            send_mail($found['user']['email'], '【パスワード再設定】LINEスタンププロンプトファクトリー', $body);
        }
        $done = true;
    }
}

auth_header('パスワード再設定');
?>
<?php if ($done): ?>
    <h1>メールを送信しました</h1>
    <div class="msg success">
        ご入力のメールアドレスが登録済みの場合、パスワード再設定用のリンクをお送りしました。<br>
        メールをご確認ください（リンクの有効期限は1時間です）。
    </div>
    <div class="links"><a href="login.php">ログイン画面へ</a></div>
<?php else: ?>
    <h1>パスワード再設定</h1>
    <p class="lead">登録済みのメールアドレスを入力してください。再設定用のリンクをお送りします。</p>
    <?php if ($error): ?><div class="msg error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post" action="forgot.php">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email_input); ?>" required autocomplete="email">
        <button type="submit" class="btn">再設定リンクを送信</button>
    </form>
    <div class="links"><a href="login.php">ログインに戻る</a></div>
<?php endif; ?>
<?php
auth_footer();
