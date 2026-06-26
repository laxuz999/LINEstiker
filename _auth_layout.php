<?php
// ============================================================
// 認証系ページ 共通レイアウト
// ============================================================
// auth_header('タイトル') … ページ冒頭のHTML（ヘッダー〜カード開始）
// auth_footer()           … カード終了〜HTML終端
// ============================================================

function auth_header($title) {
    $t = htmlspecialchars($title);
    echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$t} - LINEスタンププロンプトファクトリー</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:"Noto Sans JP",sans-serif;background:#f0faf3;display:flex;flex-direction:column;min-height:100vh;color:#1a1a1a;}
  .top-bar{background:#06C755;border-bottom:4px solid #00B900;padding:14px 24px;display:flex;align-items:center;gap:12px;}
  .top-bar .icon{font-size:22px;}
  .top-bar .title{font-size:17px;font-weight:800;color:#fff;letter-spacing:0.05em;}
  .wrap{flex:1;display:flex;justify-content:center;align-items:flex-start;padding:40px 20px;}
  .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.08);max-width:420px;width:100%;padding:36px 32px;}
  h1{font-size:21px;font-weight:800;margin-bottom:8px;}
  .lead{font-size:13px;color:#666;line-height:1.7;margin-bottom:22px;}
  label{display:block;font-size:13px;font-weight:700;margin:14px 0 6px;}
  input[type=email],input[type=password]{width:100%;padding:12px 14px;border:1.5px solid #d5ddd6;border-radius:8px;font-size:15px;font-family:inherit;}
  input:focus{outline:none;border-color:#06C755;}
  .btn{display:block;width:100%;background:#06C755;color:#fff;padding:14px;border:none;border-radius:8px;font-size:15px;font-weight:800;cursor:pointer;margin-top:22px;font-family:inherit;letter-spacing:0.05em;transition:background .2s;}
  .btn:hover{background:#00B900;}
  .links{margin-top:20px;font-size:13px;text-align:center;line-height:2;}
  .links a{color:#00B900;text-decoration:none;font-weight:700;}
  .links a:hover{text-decoration:underline;}
  .msg{padding:12px 14px;border-radius:8px;font-size:13px;line-height:1.7;margin-bottom:18px;}
  .msg.error{background:#fdecea;border:1px solid #f5c6cb;color:#a4322a;}
  .msg.success{background:#e8fdf0;border:1px solid #b0e8c4;color:#1d7a44;}
  .hint{font-size:11px;color:#999;margin-top:6px;}
</style>
</head>
<body>
  <div class="top-bar"><div class="icon">🏭</div><div class="title">LINEスタンププロンプトファクトリー</div></div>
  <div class="wrap"><div class="card">
HTML;
}

function auth_footer() {
    echo "</div></div></body></html>";
}
