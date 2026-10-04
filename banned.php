<?php
// Banned notice — distraction-free locked screen (koi menu/lectures nahi)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';

$uid = (int)($_GET['uid'] ?? 0);
$ban = null;
if ($uid > 0) {
    try {
        ensure_ban_columns();
        $st = db()->prepare("SELECT name, ban_reason, banned_at FROM users WHERE id = ? AND status = 'banned' LIMIT 1");
        $st->execute([$uid]);
        $ban = $st->fetch();
    } catch (Exception $ex) {}
}
$reason = $ban['ban_reason'] ?? null;
$support_wa = setting('whatsapp_number', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Suspended — DigitalMax LMS</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family:'Segoe UI',system-ui,sans-serif; background:#0c0912; }
  .urdu { font-family:'Noto Nastaliq Urdu','Jameel Noori Nastaleeq',serif; line-height:2.4; }
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 text-white">

<div class="w-full max-w-xl rounded-3xl overflow-hidden shadow-2xl" style="background:rgba(255,255,255,.04); border:1px solid rgba(220,38,38,.4)">
  <div class="p-6 text-center" style="background:linear-gradient(135deg,#7f1d1d,#dc2626)">
    <div class="text-5xl mb-2"><?= icon('ban',16) ?></div>
    <h1 class="text-2xl font-extrabold">ACCOUNT SUSPENDED PERMANENTLY</h1>
    <div class="urdu text-lg mt-2 text-white/95">اکاؤنٹ مستقل طور پر معطل</div>
  </div>

  <div class="p-6 md:p-8">
    <div class="text-sm text-white/75 leading-relaxed">
       <b class="text-white">ENGLISH:</b><br>
      Your account<?= $ban ? ' (<b class="text-white">' . e($ban['name']) . '</b>)' : '' ?> has been permanently banned due to a direct violation of DigitalMax Community Guidelines.
    </div>

    <div class="mt-4 rounded-xl p-4 text-sm" style="background:rgba(220,38,38,.1); border:1px solid rgba(220,38,38,.45)">
      <div class="text-xs font-bold tracking-wider text-red-300 mb-1">VIOLATION REASON</div>
      <div class="text-white font-semibold"><?= e($reason ?: 'Community guidelines violation') ?></div>
      <?php if (!empty($ban['banned_at'])): ?><div class="text-xs text-white/40 mt-1">Banned on <?= e(date('d M Y · h:i A', strtotime($ban['banned_at']))) ?></div><?php endif; ?>
    </div>

    <div class="text-sm text-white/75 leading-relaxed mt-4">
      <b class="text-white">Note:</b> As per our zero-tolerance policy, all LMS and community access has been permanently revoked with <b class="text-red-300">NO REFUND</b>.
    </div>

    <hr class="my-5" style="border-color:rgba(255,255,255,.1)">

    <div class="urdu text-white/80 text-right" dir="rtl">
      آپ کا اکاؤنٹ ڈیجیٹل میکس کمیونٹی کے اصول و ضوابط کی خلاف ورزی کی بنا پر مستقل طور پر بلاک کر دیا گیا ہے۔
    </div>
    <div class="text-sm text-white/60 mt-3 text-right" dir="rtl">
      <b class="text-white/85">وجہ:</b> <?= e($reason ?: 'کمیونٹی اصولوں کی خلاف ورزی') ?>
    </div>
    <div class="urdu text-white/70 text-right mt-3" dir="rtl">
      نوٹ: زیرو ٹالرنس پالیسی کے تحت تمام رسائی ختم کر دی گئی ہے اور کسی قسم کی فیس واپس نہیں کی جائے گی۔
    </div>

    <div class="mt-6 text-center text-sm text-white/50">
      Support inquiry ke liye:
      <?php if ($support_wa): ?>
        <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $support_wa)) ?>?text=Assalam-o-Alaikum,%20mera%20account%20suspend%20hua%20hai.%20Review%20ki%20request%20hai." target="_blank" rel="noopener" class="underline font-semibold text-white/80">Contact Admin Support →</a>
      <?php else: ?>
        <span class="text-white/70">Admin se rabta karein.</span>
      <?php endif; ?>
      <div class="mt-3"><a href="login.php" class="underline text-white/40 text-xs">← Back to login</a></div>
    </div>
  </div>
</div>

</body>
</html>
