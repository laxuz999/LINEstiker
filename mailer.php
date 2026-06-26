<?php
// ============================================================
// メール送信 共通関数（PHP mail() ラッパー）
// ============================================================
// 送信元は orders@laxuz.xyz。
// ※laxuz.xyz は SPF(include:_spf.conoha.ne.jp) と DKIM が設定済みのため
//   ConoHaサーバーからの送信がGmail等に認証される。
//   laxuz.net はGoogle送信専用の認証設定のため、ConoHaから送ると弾かれる。
// 届かない場合に外部SMTP/APIへ差し替えやすいよう、送信処理を
// この1関数に集約している。呼び出し側は send_mail() だけ使う。
// ============================================================

// サイトのベースURL（メール本文のリンク生成に使用）
if (!defined('SITE_BASE_URL')) {
    define('SITE_BASE_URL', 'https://laxuz.xyz/LINEstiker/');
}
// 送信元アドレス（SPF/DKIM認証済みの laxuz.xyz を使う）
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', 'orders@laxuz.xyz');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'LINEスタンププロンプトファクトリー');
}

/**
 * メールを送信する。
 *
 * @param string $to      宛先メールアドレス
 * @param string $subject 件名（UTF-8のプレーンテキスト）
 * @param string $body    本文（UTF-8のプレーンテキスト）
 * @return bool 送信に成功したら true
 */
function send_mail($to, $subject, $body) {
    // 不正な宛先は拒否
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    // 日本語の件名・差出人名はMIMEエンコードが必要
    mb_internal_encoding('UTF-8');
    $encoded_subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\n");
    $encoded_from_name = mb_encode_mimeheader(MAIL_FROM_NAME, 'UTF-8', 'B', "\n");

    $headers = [];
    $headers[] = 'From: ' . $encoded_from_name . ' <' . MAIL_FROM . '>';
    $headers[] = 'Reply-To: ' . MAIL_FROM;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    // エンベロープFrom（-f）を指定すると迷惑メール判定が緩和されやすい
    $additional_params = '-f' . MAIL_FROM;

    return @mail($to, $encoded_subject, $body, implode("\r\n", $headers), $additional_params);
}
