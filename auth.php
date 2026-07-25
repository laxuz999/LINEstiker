<?php
// ============================================================
// 認証・アカウント 共通基盤
// ============================================================
// - PHPセッション（Secure / HttpOnly / SameSite=Lax）
// - users.json の読み書き（flock排他制御）
// - アカウント作成・更新・検索
// - メール確認 / 課金 / トライアル状態の判定
//
// 各ページの冒頭で require_once してから使う。
// ============================================================

require_once __DIR__ . '/mailer.php';

// ── 定数 ──
if (!defined('TRIAL_DAYS')) {
    define('TRIAL_DAYS', 7);  // 無料トライアル日数（アカウント単位）
}
$GLOBALS['__users_file'] = __DIR__ . '/data/users.json';

// ── 管理者許可リスト（ハードコード）──
const ADMIN_EMAILS = ['zz999999999@gmail.com'];

/**
 * このユーザーが管理者許可リストに含まれるか。
 * @param array $user ユーザー配列（'email'キーを持つ）
 */
function is_admin_user($user) {
    $email = normalize_email($user['email'] ?? '');
    $admin_emails_normalized = array_map('normalize_email', ADMIN_EMAILS);
    return in_array($email, $admin_emails_normalized, true);
}

// ── セッション開始（安全なクッキー設定）──
function auth_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── users.json 読み書き（flockで排他）──
function load_users() {
    $f = $GLOBALS['__users_file'];
    if (!file_exists($f)) return [];
    $fp = fopen($f, 'r');
    if (!$fp) return [];
    $data = '';
    if (flock($fp, LOCK_SH)) {
        while (!feof($fp)) $data .= fread($fp, 8192);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return json_decode($data, true) ?: [];
}

function save_users($users) {
    $dir = dirname($GLOBALS['__users_file']);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fp = fopen($GLOBALS['__users_file'], 'c+');
    if (!$fp) return false;
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($users, JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = true;
    }
    fclose($fp);
    return $ok;
}

// ── 検索・取得 ──
function normalize_email($email) {
    return strtolower(trim($email));
}

/**
 * 重複判定専用の正規化。
 * Gmail/Googlemailは「+alias」「ドット」の違いを無視して同一アドレスとみなす
 * （実際に届く受信箱が同じため、これらを別アカウント扱いにするとトライアルの
 * 使い回し不正を許してしまう）。
 * 保存する email フィールド自体は normalize_email() のまま変更しない。
 */
function canonicalize_email($email) {
    $email = normalize_email($email);
    $at = strrpos($email, '@');
    if ($at === false) return $email;
    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);
    if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
        $plus = strpos($local, '+');
        if ($plus !== false) {
            $local = substr($local, 0, $plus);
        }
        $local  = str_replace('.', '', $local);
        $domain = 'gmail.com';
    }
    return $local . '@' . $domain;
}

function find_user_by_email($email) {
    $target = canonicalize_email($email);
    foreach (load_users() as $id => $u) {
        if (canonicalize_email($u['email'] ?? '') === $target) {
            return ['id' => $id, 'user' => $u];
        }
    }
    return null;
}

function get_user($id) {
    $users = load_users();
    return $users[$id] ?? null;
}

function find_user_by_stripe_customer($customer_id) {
    if (!$customer_id) return null;
    foreach (load_users() as $id => $u) {
        if (($u['stripe_customer_id'] ?? null) === $customer_id) {
            return ['id' => $id, 'user' => $u];
        }
    }
    return null;
}

function is_email_taken($email) {
    return find_user_by_email($email) !== null;
}

// ── 作成・更新 ──
/**
 * 新規アカウントを作成（メール未確認＝pending状態）。
 * @return array ['id'=>string, 'user'=>array, 'verify_token'=>string]
 */
function create_user($email, $password) {
    $users = load_users();
    $id = bin2hex(random_bytes(16));
    $verify_token = bin2hex(random_bytes(32));
    $users[$id] = [
        'email'                => normalize_email($email),
        'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
        'created_at'           => time(),            // トライアル起点
        'email_verified'       => false,            // メール確認済みか
        'verify_token'         => $verify_token,    // 確認リンク用
        'reset_token'          => null,             // パスワードリセット用
        'reset_token_expires'  => null,
        'stripe_customer_id'   => null,
        'subscription_status'  => 'trial',          // trial|active|canceled|past_due
        'subscription_period_end' => null,
    ];
    save_users($users);
    return ['id' => $id, 'user' => $users[$id], 'verify_token' => $verify_token];
}

/**
 * 指定アカウントのフィールドを部分更新する。
 */
function update_user($id, $fields) {
    $users = load_users();
    if (!isset($users[$id])) return false;
    foreach ($fields as $k => $v) {
        $users[$id][$k] = $v;
    }
    return save_users($users);
}

// ── ログイン状態 ──
function login_user($id) {
    auth_session_start();
    session_regenerate_id(true);  // セッション固定化攻撃対策
    $_SESSION['user_id'] = $id;
}

function logout_user() {
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * 現在ログイン中のアカウントを返す。未ログインなら null。
 * @return array|null ['id'=>string, 'user'=>array]
 */
function current_user() {
    auth_session_start();
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) return null;
    $u = get_user($id);
    if (!$u) return null;  // アカウントが削除済み等
    return ['id' => $id, 'user' => $u];
}

// ── アクセス権・トライアル判定 ──
/**
 * トライアル残り日数（負なら期限切れ）。
 */
function trial_days_left($user) {
    $passed = floor((time() - ($user['created_at'] ?? time())) / 86400);
    return TRIAL_DAYS - $passed;
}

/**
 * このアカウントがツールを利用できるか。
 * 条件：メール確認済み かつ（課金中 または トライアル期間内）。
 */
function user_has_access($user) {
    if (empty($user['email_verified'])) return false;
    if (($user['subscription_status'] ?? '') === 'active') return true;
    if (($user['subscription_status'] ?? '') === 'trial' && trial_days_left($user) > 0) return true;
    return false;
}

/**
 * 課金済み（サブスク有効）か。
 */
function user_is_paid($user) {
    return ($user['subscription_status'] ?? '') === 'active';
}

// ── CSRF対策 ──
function csrf_token() {
    auth_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check() {
    auth_session_start();
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

// ── 簡易ログイン試行レート制限（IP+メール単位）──
function rate_limit_check($key, $max = 5, $window = 900) {
    auth_session_start();
    $now = time();
    $bucket = $_SESSION['rl'][$key] ?? ['count' => 0, 'start' => $now];
    if ($now - $bucket['start'] > $window) {
        $bucket = ['count' => 0, 'start' => $now];  // ウィンドウ経過でリセット
    }
    $_SESSION['rl'][$key] = $bucket;
    return $bucket['count'] < $max;
}

function rate_limit_hit($key) {
    auth_session_start();
    $_SESSION['rl'][$key]['count'] = ($_SESSION['rl'][$key]['count'] ?? 0) + 1;
}
