<?php
declare(strict_types=1);

namespace App\Models;

final readonly class Email
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param array<string, string> $headers
     */
    public function __construct(
        public array  $to,
        public string $subject,
        public string $body,
        public bool   $isHtml = true,
        public array  $cc = [],
        public array  $bcc = [],
        public array  $headers = [],
    ) {}

    public static function to(string $address, string $subject, string $body, bool $isHtml = true): self
    {
        return new self(
            to:      [$address],
            subject: $subject,
            body:    $body,
            isHtml:  $isHtml,
        );
    }
}