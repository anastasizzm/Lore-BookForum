<?php
declare(strict_types=1);

namespace App\Lib\Settings;

final readonly class MailOwner
{
    public function __construct(
        public string $address,
        public string $name
    ){}
}

final readonly class MailSettings 
{
    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $encryption,
        public MailOwner $from
    ){}
}