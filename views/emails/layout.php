<?php
/** @var string $preheader */
/** @var string $content */
/** @var string $logoUrl */
/** @var string $address */
/** @var string $privacyUrl */
/** @var string $contactUrl */
$font = 'Figtree, ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>atwebtech</title>
</head>
<body style="margin:0;padding:0;background:#F3F0E8;color:#213130;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;"><?= e($preheader) ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#F3F0E8;min-height:100vh;">
  <tr>
    <td align="center" style="padding:48px 16px 36px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:520px;background:#ffffff;">
        <tr>
          <td align="center" style="padding:44px 40px 8px;font-family:<?= e($font) ?>;">
            <a href="<?= e(absolute_url('/')) ?>" style="text-decoration:none;color:#213130;">
              <img src="<?= e($logoUrl) ?>" width="36" height="36" alt="atwebtech" style="display:block;margin:0 auto 10px;border:0;outline:none;width:36px;height:36px;">
              <span style="font-family:<?= e($font) ?>;font-size:18px;font-weight:600;letter-spacing:-0.02em;color:#213130;">atwebtech</span>
            </a>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 48px 44px;font-family:<?= e($font) ?>;font-size:16px;line-height:1.55;color:#213130;">
            <?= $content ?>
          </td>
        </tr>
      </table>
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;max-width:520px;">
        <tr>
          <td align="center" style="padding:28px 24px 8px;font-family:<?= e($font) ?>;font-size:13px;line-height:1.5;color:#8A877C;">
            <p style="margin:0 0 8px;">Sent by the team at atwebtech</p>
            <p style="margin:0 0 14px;"><?= e($address) ?></p>
            <p style="margin:0;">
              <a href="<?= e($privacyUrl) ?>" style="color:#8A877C;text-decoration:underline;">Privacy</a>
              <span aria-hidden="true"> &nbsp;|&nbsp; </span>
              <a href="<?= e($contactUrl) ?>" style="color:#8A877C;text-decoration:underline;">Contact</a>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td height="8" bgcolor="#B0EC9C" style="height:8px;background:#B0EC9C;font-size:0;line-height:0;">&nbsp;</td>
  </tr>
</table>
</body>
</html>
