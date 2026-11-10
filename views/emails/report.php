<?php
/** @var string $who */
/** @var string $period */
/** @var array<string,string> $stats */
/** @var string $url */
/** @var string $color */
/** @var bool $whiteLabel */
$font = 'Figtree, ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
?>
<p style="margin:0 0 6px;font-size:13px;color:#5E6461;">Social media report</p>
<p style="margin:0 0 22px;font-size:20px;font-weight:600;"><?= e($period) ?></p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 26px;">
<?php foreach (array_chunk($stats, 2, true) as $pair): ?>
  <tr>
<?php foreach ($pair as $label => $value): ?>
    <td width="50%" style="padding:6px;">
      <div style="border:1px solid #E5E3DD;border-left:4px solid <?= e($color) ?>;border-radius:8px;padding:12px 14px;">
        <div style="font-size:22px;font-weight:700;color:#141A19;"><?= e($value) ?></div>
        <div style="font-size:12.5px;color:#5E6461;"><?= e($label) ?></div>
      </div>
    </td>
<?php endforeach; ?>
<?php if (count($pair) === 1): ?><td width="50%"></td><?php endif; ?>
  </tr>
<?php endforeach; ?>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" align="center" style="border-collapse:collapse;margin:0 auto 24px;">
  <tr>
    <td align="center" bgcolor="<?= e($color) ?>" style="background:<?= e($color) ?>;border-radius:999px;">
      <a href="<?= e($url) ?>" style="display:inline-block;padding:12px 22px;font-family:<?= e($font) ?>;font-size:15px;font-weight:600;line-height:1;color:#ffffff;text-decoration:none;border-radius:999px;">Open the full report</a>
    </td>
  </tr>
</table>
<p style="margin:0;font-size:13px;color:#5E6461;">The report shows every post, its stats, and the best times to post. You can save it as a PDF from your browser.<?= $whiteLabel ? '' : ' Sent by atwebtech for ' . e($who) . '.' ?></p>
