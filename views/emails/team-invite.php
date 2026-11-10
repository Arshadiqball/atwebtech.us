<?php
/** @var string $url */
/** @var string $owner */
/** @var string $name */
/** @var string $role */
/** @var int $days */
$font = 'Figtree, ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
?>
<p style="margin:0 0 16px;">Hi <?= e($name !== '' ? $name : 'there') ?>,</p>
<p style="margin:0 0 28px;"><?= e($owner) ?> invited you to their atwebtech workspace as <b><?= e($role) ?></b>, to plan and publish social media posts together.</p>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="border-collapse:collapse;margin:0 auto 28px;">
  <tr>
    <td align="center" bgcolor="#B0EC9C" style="background:#B0EC9C;border-radius:999px;">
      <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 22px;font-family:<?= e($font) ?>;font-size:15px;font-weight:600;line-height:1;color:#213130;text-decoration:none;border-radius:999px;">Join the workspace</a>
    </td>
  </tr>
</table>
<p style="margin:0 0 16px;">The link works once, for <?= (int) $days ?> days. Sign up or log in with this email address.</p>
<p style="margin:0 0 16px;">If the button does not open, copy this link into your browser:<br><a href="<?= e($url) ?>" style="color:#2F6A43;word-break:break-all;"><?= e($url) ?></a></p>
<p style="margin:0;">Cheers,<br>The atwebtech team</p>
