<?php
// ============================================================
// 管理画面（閲覧専用）
// ============================================================
// - 登録者一覧を確認するための簡易ダッシュボード
// - 許可リストに載っているメールアドレスのログインユーザーのみ閲覧可
// - 編集・削除・状態変更などの操作機能は持たない（閲覧専用）
// ============================================================
require_once __DIR__ . '/auth.php';

// ── 管理者許可リスト（ハードコード）──
const ADMIN_EMAILS = ['zz999999999@gmail.com'];

// 未ログインならログイン画面へ
$me = current_user();
if (!$me) {
    header('Location: login.php');
    exit;
}
$my_email = normalize_email($me['user']['email'] ?? '');

$admin_emails_normalized = array_map('normalize_email', ADMIN_EMAILS);
$is_admin = in_array($my_email, $admin_emails_normalized, true);

// ── 状態ラベル変換 ──
function admin_status_label($status) {
    switch ($status) {
        case 'trial':    return 'トライアル中';
        case 'active':   return '課金中';
        case 'canceled': return '解約済み';
        case 'past_due': return '支払い遅延';
        default:         return $status !== '' ? htmlspecialchars((string)$status) : '不明';
    }
}

if ($is_admin) {
    $users = load_users();

    // 登録日（created_at）の新しい順にソート
    uasort($users, function ($a, $b) {
        return ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0);
    });

    // サマリー集計
    $total_count    = count($users);
    $trial_count    = 0;
    $active_count   = 0;
    $inactive_count = 0; // canceled + past_due
    foreach ($users as $u) {
        $status = $u['subscription_status'] ?? '';
        if ($status === 'trial') {
            $trial_count++;
        } elseif ($status === 'active') {
            $active_count++;
        } elseif ($status === 'canceled' || $status === 'past_due') {
            $inactive_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>管理画面 - LINEスタンププロンプトファクトリー</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:"Noto Sans JP",sans-serif;background:#f0faf3;display:flex;flex-direction:column;min-height:100vh;color:#1a1a1a;}
  .top-bar{background:#06C755;border-bottom:4px solid #00B900;padding:14px 24px;display:flex;align-items:center;gap:12px;}
  .top-bar .icon{font-size:22px;}
  .top-bar .title{font-size:17px;font-weight:800;color:#fff;letter-spacing:0.05em;}
  .wrap{flex:1;padding:32px 20px;display:flex;justify-content:center;}
  .inner{max-width:1080px;width:100%;}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);padding:28px 32px;margin-bottom:24px;}
  h1{font-size:20px;font-weight:800;margin-bottom:18px;}
  .summary{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:8px;}
  .summary .box{flex:1;min-width:140px;background:#f0faf3;border:1.5px solid #d5ddd6;border-radius:10px;padding:14px 16px;}
  .summary .box .num{font-size:24px;font-weight:800;color:#06C755;}
  .summary .box .label{font-size:12px;color:#666;margin-top:4px;}
  .table-wrap{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;font-size:13px;}
  th,td{padding:10px 12px;border-bottom:1px solid #e5e5e5;text-align:left;white-space:nowrap;}
  th{background:#f0faf3;font-weight:700;color:#333;}
  tr:hover td{background:#fafafa;}
  .badge{display:inline-block;padding:3px 10px;border-radius:100px;font-size:11px;font-weight:700;}
  .badge.trial{background:#fff6e0;color:#a67c00;}
  .badge.active{background:#e8fdf0;color:#1d7a44;}
  .badge.canceled{background:#f0f0f0;color:#666;}
  .badge.past_due{background:#fdecea;color:#a4322a;}
  .yes{color:#1d7a44;font-weight:700;}
  .no{color:#999;}
  .msg{padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.7;background:#fdecea;border:1px solid #f5c6cb;color:#a4322a;}
  .links{margin-top:8px;font-size:13px;text-align:center;line-height:2;}
  .links a{color:#00B900;text-decoration:none;font-weight:700;}
  .links a:hover{text-decoration:underline;}
</style>
</head>
<body>
  <div class="top-bar"><div class="icon">🏭</div><div class="title">LINEスタンププロンプトファクトリー - 管理画面</div></div>
  <div class="wrap"><div class="inner">
<?php if (!$is_admin): ?>
    <div class="card">
      <div class="msg">アクセス権がありません。</div>
    </div>
<?php else: ?>
    <div class="card">
      <h1>登録者一覧</h1>
      <div class="summary">
        <div class="box"><div class="num"><?php echo (int)$total_count; ?></div><div class="label">総登録者数</div></div>
        <div class="box"><div class="num"><?php echo (int)$trial_count; ?></div><div class="label">トライアル中</div></div>
        <div class="box"><div class="num"><?php echo (int)$active_count; ?></div><div class="label">課金中</div></div>
        <div class="box"><div class="num"><?php echo (int)$inactive_count; ?></div><div class="label">解約・支払い遅延</div></div>
      </div>
    </div>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>メールアドレス</th>
              <th>登録日</th>
              <th>メール確認</th>
              <th>状態</th>
              <th>トライアル残り日数</th>
              <th>Stripe連携</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($users as $u):
    $status = $u['subscription_status'] ?? '';
    $created = isset($u['created_at']) ? date('Y-m-d H:i', (int)$u['created_at']) : '-';
    $verified = !empty($u['email_verified']) ? '<span class="yes">済</span>' : '<span class="no">未</span>';
    $trial_left = '-';
    if ($status === 'trial') {
        $trial_left = trial_days_left($u) . '日';
    }
    $stripe_linked = !empty($u['stripe_customer_id']) ? '<span class="yes">連携済み</span>' : '<span class="no">未連携</span>';
?>
            <tr>
              <td><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($created); ?></td>
              <td><?php echo $verified; ?></td>
              <td><span class="badge <?php echo htmlspecialchars($status); ?>"><?php echo admin_status_label($status); ?></span></td>
              <td><?php echo htmlspecialchars((string)$trial_left); ?></td>
              <td><?php echo $stripe_linked; ?></td>
            </tr>
<?php endforeach; ?>
<?php if ($total_count === 0): ?>
            <tr><td colspan="6" style="text-align:center;color:#999;">登録者がいません。</td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
<?php endif; ?>
    <div class="links">
      <a href="/LINEstiker/">← サービスに戻る</a><br>
      <a href="logout.php">ログアウト</a>
    </div>
  </div></div>
</body>
</html>
