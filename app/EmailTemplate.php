<?php

declare(strict_types=1);

/** Customer email HTML. The confirm message uses the shared card layout. */
final class EmailTemplate
{
    /** @return array{subject: string, html: string, text: string} */
    public static function confirm(string $confirmUrl): array
    {
        $address = (string) config('contact.address');
        $html = self::layout(
            'Confirm your email address for atwebtech.',
            self::capture('confirm', ['confirmUrl' => $confirmUrl])
        );
        $text = "Hi there,\n\n"
            . "Please confirm this email address so we know it belongs to you:\n"
            . $confirmUrl . "\n\n"
            . "Confirming this address keeps the account secure and makes sure you can use the features included in your plan.\n\n"
            . "Questions? " . absolute_url('/contact') . "\n\n"
            . "Cheers,\nThe atwebtech team\n\n"
            . $address . "\n";

        return [
            'subject' => 'Confirm your email address',
            'html' => $html,
            'text' => $text,
        ];
    }

    /** @return array{subject: string, html: string, text: string} */
    public static function invite(string $inviteUrl, string $owner, string $name, int $days): array
    {
        $html = self::layout(
            $owner . ' invited you to review posts on atwebtech.',
            self::capture('invite', ['inviteUrl' => $inviteUrl, 'owner' => $owner, 'name' => $name, 'days' => $days])
        );
        $text = 'Hi ' . ($name !== '' ? $name : 'there') . ",\n\n"
            . $owner . " invited you to review their social media posts on atwebtech. Open this link to approve posts or ask for changes. You don't need an account:\n"
            . $inviteUrl . "\n\n"
            . 'The link is private to you and works for ' . $days . " days.\n\n"
            . "Cheers,\nThe atwebtech team\n\n"
            . (string) config('contact.address') . "\n";
        return ['subject' => $owner . ' invited you to review posts', 'html' => $html, 'text' => $text];
    }

    /** @return array{subject: string, html: string, text: string} */
    public static function passwordReset(string $url, int $minutes): array
    {
        $html = self::layout('Choose a new password for atwebtech.', self::capture('notice', [
            'title' => 'Reset your password', 'body' => 'Someone asked to reset the password for this atwebtech account. If that was you, choose a new one. The link works once, for ' . $minutes . ' minutes. If it wasn’t you, ignore this email: your password stays the same.',
            'url' => $url, 'cta' => 'Choose a new password', 'where' => '', 'prefs' => false,
        ]));
        $text = "Reset your atwebtech password\n\nOpen this link to choose a new password (works once, for " . $minutes . " minutes):\n" . $url . "\n\nIf you didn't ask for this, ignore this email. Your password stays the same.\n";
        return ['subject' => 'Reset your atwebtech password', 'html' => $html, 'text' => $text];
    }

    /** A notification email: what happened, where, and a button to open it. */
    public static function notice(string $title, string $body, string $url, string $where): array
    {
        $html = self::layout($title, self::capture('notice', ['title' => $title, 'body' => $body, 'url' => $url, 'cta' => 'Open in atwebtech', 'where' => $where, 'prefs' => true]));
        $text = $title . ($where !== '' ? ' (' . $where . ')' : '') . "\n\n" . $body . "\n\n" . $url . "\n\nChoose which emails you get: " . absolute_url('/app/account#notifications') . "\n";
        return ['subject' => $title, 'html' => $html, 'text' => $text];
    }

    /** @return array{subject: string, html: string, text: string} */
    public static function teamInvite(string $url, string $owner, string $name, string $role, int $days): array
    {
        $html = self::layout(
            $owner . ' invited you to their atwebtech workspace.',
            self::capture('team-invite', ['url' => $url, 'owner' => $owner, 'name' => $name, 'role' => $role, 'days' => $days])
        );
        $text = 'Hi ' . ($name !== '' ? $name : 'there') . ",\n\n"
            . $owner . ' invited you to their atwebtech workspace as ' . $role . ". Open this link to join:\n"
            . $url . "\n\n"
            . 'The link works once, for ' . $days . " days. Use this email address to sign up or log in.\n\n"
            . "Cheers,\nThe atwebtech team\n\n"
            . (string) config('contact.address') . "\n";
        return ['subject' => $owner . ' invited you to their atwebtech workspace', 'html' => $html, 'text' => $text];
    }

    /**
     * Scheduled branded report: headline numbers and a link to the full report.
     * @return array{subject: string, html: string, text: string}
     */
    public static function report(array $in, array $brand, string $url, ?string $logoUrl): array
    {
        $who = $brand['name'] ?? ($brand['owner'] !== '' ? $brand['owner'] : 'Your');
        $period = $in['from']->format('M j') . ' – ' . $in['to']->format('M j, Y');
        $m = $in['metrics']['now'];
        $stats = array_filter([
            'Posts published' => (string) $in['sent'],
            'Followers' => $m['followers'] !== null ? number_format((int) $m['followers']) : null,
            'Reactions' => $m['reactions'] !== null ? number_format((int) $m['reactions']) : null,
            'Comments' => $m['comments'] !== null ? number_format((int) $m['comments']) : null,
            'Views' => ($m['impressions'] ?? $m['video_views']) !== null ? number_format((int) ($m['impressions'] ?? $m['video_views'])) : null,
            'Engagement rate' => $m['eng_rate'] !== null ? rtrim(rtrim(number_format((float) $m['eng_rate'], 2), '0'), '.') . '%' : null,
        ], static fn ($v) => $v !== null);
        $subject = $who . ' social media report · ' . $period;
        $html = self::layout($subject, self::capture('report', ['who' => $who, 'period' => $period, 'stats' => $stats, 'url' => $url, 'color' => $brand['color'], 'whiteLabel' => $brand['white_label']]), $brand['white_label'] ? ($brand['name'] ?? '') : null, $logoUrl);
        $text = $subject . "\n\n";
        foreach ($stats as $k => $v) {
            $text .= $k . ': ' . $v . "\n";
        }
        $text .= "\nFull report: " . $url . "\n";
        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }

    private static function layout(string $preheader, string $content, ?string $brandName = null, ?string $brandLogo = null): string
    {
        return self::capture('layout', [
            'preheader' => $preheader,
            'content' => $content,
            'brandName' => $brandName,
            'brandLogo' => $brandLogo,
            'logoUrl' => absolute_url('/assets/img/logo-mark.png'),
            'address' => (string) config('contact.address'),
            'privacyUrl' => absolute_url('/privacy'),
            'contactUrl' => absolute_url('/contact'),
        ]);
    }

    private static function capture(string $name, array $vars): string
    {
        $file = BASE_PATH . '/views/emails/' . $name . '.php';
        ob_start();
        (static function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        })($file, $vars);
        return (string) ob_get_clean();
    }
}
