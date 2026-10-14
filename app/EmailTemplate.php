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

    private static function layout(string $preheader, string $content): string
    {
        return self::capture('layout', [
            'preheader' => $preheader,
            'content' => $content,
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
