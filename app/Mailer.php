<?php

declare(strict_types=1);

/**
 * Internal notification mail. Sent through the SMTP account in .env.
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
        if ((string) config('mail.host') === '' || (string) config('mail.user') === '' || (string) config('mail.pass') === '') {
            error_log('Mailer: SMTP is enabled but host, user, or password is missing.');
            return;
        }

        $lines = [];
        foreach ($fields as $label => $value) {
            $clean = str_replace(["\r\n", "\r"], "\n", (string) $value);
            $lines[] = $label . ': ' . $clean;
        }
        $safeSubject = '[atwebtech] ' . trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject);

        try {
            self::deliver($from, $to, $safeSubject, implode("\n", $lines));
        } catch (Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
        }
    }

    /** Customer mail, with the HTML card and a plain-text copy. */
    public static function send(string $to, string $subject, string $html, string $text): void
    {
        $from = (string) config('mail.from');
        if (!config('mail.enabled') || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if ((string) config('mail.host') === '' || (string) config('mail.user') === '' || (string) config('mail.pass') === '') {
            error_log('Mailer: SMTP is enabled but host, user, or password is missing.');
            return;
        }
        $safeSubject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? $subject);
        try {
            self::deliver($from, $to, $safeSubject, $text, $html);
        } catch (Throwable $e) {
            error_log('Mailer: ' . $e->getMessage());
        }
    }

    private static function deliver(string $from, string $to, string $subject, string $body, ?string $html = null): void
    {
        $host = (string) config('mail.host');
        $port = (int) config('mail.port');
        $socket = self::connect($host, $port);
        try {
            self::expect($socket, 220);
            self::command($socket, 'EHLO atwebtech.us', 250);
            if ((string) config('mail.encryption') === 'starttls') {
                self::command($socket, 'STARTTLS', 220);
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('SMTP STARTTLS failed.');
                }
                self::command($socket, 'EHLO atwebtech.us', 250);
            }
            self::command($socket, 'AUTH LOGIN', 334);
            self::command($socket, base64_encode((string) config('mail.user')), 334);
            self::command($socket, base64_encode((string) config('mail.pass')), 235);
            self::command($socket, 'MAIL FROM:<' . $from . '>', 250);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', 354);
            self::write($socket, self::message($from, $to, $subject, $body, $html) . "\r\n.\r\n");
            self::expect($socket, 250);
            self::command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private static function connect(string $host, int $port)
    {
        $context = stream_context_create([
            'ssl' => [
                'peer_name' => $host,
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $socket = stream_socket_client(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }
        stream_set_timeout($socket, 15);
        return $socket;
    }

    private static function message(string $from, string $to, string $subject, string $body, ?string $html = null): string
    {
        $fromName = self::headerName((string) config('mail.from_name'));
        $replyTo = (string) config('mail.reply_to');
        $replyName = self::headerName((string) config('mail.reply_to_name'));
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = $from;
            $replyName = $fromName;
        }
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $fromName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Reply-To: ' . $replyName . ' <' . $replyTo . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
        ];
        $text = self::dotStuff($body);
        if ($html === null || $html === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            return implode("\r\n", $headers) . "\r\n\r\n" . $text;
        }
        $boundary = 'awt_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $text,
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            self::dotStuff($html),
            '--' . $boundary . '--',
        ];
        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private static function dotStuff(string $body): string
    {
        $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body)) ?? $body;
        return str_replace("\n", "\r\n", $body);
    }

    private static function headerName(string $name): string
    {
        $name = trim(str_replace(["\r", "\n"], '', $name));
        if ($name === '') {
            return 'atwebtech';
        }
        return '"' . addcslashes($name, '"\\') . '"';
    }

    /** @param resource $socket */
    private static function command($socket, string $command, int|array $expect): void
    {
        self::write($socket, $command . "\r\n");
        self::expect($socket, $expect);
    }

    /** @param resource $socket */
    private static function write($socket, string $data): void
    {
        $written = fwrite($socket, $data);
        if ($written === false || $written < strlen($data)) {
            throw new RuntimeException('SMTP write failed.');
        }
    }

    /** @param resource $socket */
    private static function expect($socket, int|array $expect): void
    {
        $allowed = (array) $expect;
        $lines = [];
        do {
            $line = fgets($socket, 2048);
            if ($line === false) {
                throw new RuntimeException('SMTP server closed the connection.');
            }
            $lines[] = rtrim($line, "\r\n");
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($lines[0], 0, 3);
        if (!in_array($code, $allowed, true)) {
            throw new RuntimeException('SMTP error ' . $code . '.');
        }
    }
}
