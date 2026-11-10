<?php
/** @var string $title */
/** @var string $body */
/** @var string $url */
/** @var string $cta */
/** @var string $where */
/** @var bool $prefs */
$font = 'Figtree, ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
?>
<?php if ($where !== ''): ?>
<p style="margin:0 0 6px;font-size:13px;color:#5E6461;"><?= e($where) ?></p>
<?php endif; ?>
<p style="margin:0 0 14px;font-size:19px;font-weight:600;line-height:1.35;"><?= e($title) ?></p>
<p style="margin:0 0 26px;"><?= e($body) ?></p>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="border-collapse:collapse;margin:0 auto 26px;">
  <tr>
    <td align="center" bgcolor="#B0EC9C" style="background:#B0EC9C;border-radius:999px;">
      <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 22px;font-family:<?= e($font) ?>;font-size:15px;font-weight:600;line-height:1;color:#213130;text-decoration:none;border-radius:999px;"><?= e($cta) ?></a>
    </td>
  </tr>
</table>
<p style="margin:0 0 12px;font-size:13px;color:#5E6461;">If the button does not open, copy this link into your browser:<br><a href="<?= e($url) ?>" style="color:#2F6A43;word-break:break-all;"><?= e($url) ?></a></p>
<?php if ($prefs): ?>
<p style="margin:0;font-size:13px;color:#5E6461;">You can choose which emails you get under <a href="<?= e(absolute_url('/app/account#notifications')) ?>" style="color:#2F6A43;">Account → Notifications</a>.</p>
<?php endif; ?>
