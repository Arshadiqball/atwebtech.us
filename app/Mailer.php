<?php

declare(strict_types=1);

/**
 * Optional internal notification via the host's PHP mail() transport.
 * Failures are logged and never block a submission that has already been saved.
 */
final class Mailer
{
    public static function notify(string $subject, array $fields): void
    {
        $to = (string) config('mail.notify_to');
        $from = (string) config('mail.from');
        if (!config('mail.enabled') || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $lines = [];
        foreach ($fields as $label => $value) {
            $lines[] = $label . ': ' . str_replace(["\r", "\n"], ' ', (string) $value);
        }
        $headers = [
            'From: atwebtech <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $safeSubject = '[atwebtech] ' . preg_replace('/[\r\n]+/', ' ', $subject);

        try {
            if (!mail($to, $safeSubject, implode("\n", $lines), implode("\r\n", $headers))) {
                error_log('Mailer: mail() returned false for notification "' . $safeSubject . '"');
            }
        } catch (Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
        }
    }
}
