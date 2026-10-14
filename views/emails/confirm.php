<?php
/** @var string $confirmUrl */
$font = 'Figtree, ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
?>
<p style="margin:0 0 16px;">Hi there,</p>
<p style="margin:0 0 28px;">Please confirm this email address so we know it belongs to you. The button below opens atwebtech and finishes that check.</p>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="border-collapse:collapse;margin:0 auto 28px;">
  <tr>
    <td align="center" bgcolor="#B0EC9C" style="background:#B0EC9C;border-radius:999px;">
      <a href="<?= e($confirmUrl) ?>" style="display:inline-block;padding:12px 22px;font-family:<?= e($font) ?>;font-size:15px;font-weight:600;line-height:1;color:#213130;text-decoration:none;border-radius:999px;">Confirm email</a>
    </td>
  </tr>
</table>
<p style="margin:0 0 16px;">Confirming this address keeps the account secure and makes sure you can use the features included in your plan.</p>
<p style="margin:0 0 16px;">If the button does not open, copy this link into your browser:<br><a href="<?= e($confirmUrl) ?>" style="color:#2F6A43;word-break:break-all;"><?= e($confirmUrl) ?></a></p>
<p style="margin:0 0 16px;">Questions about this message? Write to us on the <a href="<?= e(absolute_url('/contact')) ?>" style="color:#2F6A43;">contact page</a>.</p>
<p style="margin:0;">Cheers,<br>The atwebtech team</p>
