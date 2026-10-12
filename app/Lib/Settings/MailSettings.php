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
    private const DATA_KEY = 'mail';

    public function __construct(
        public string $host,
        public int $port,
        public string $username,
        public string $password,
        public string $encryption,
        public MailOwner $from
    ){}

    public static function fromData(array $data) : self
    {
        $mail = $data[self::DATA_KEY] ?? [];
        return new self(
            host: $mail['host'] ?? 'localhost',
            port: (int)($mail['port'] ?? 587),
            username: $mail['username'] ?? '',
            password: $mail['password'] ?? '',
            encryption: $mail['encryption'] ?? 'tls',
            from: new MailOwner(
                address: $mail['from']['address'] ?? 'noreply@localhost',
                name: $mail['from']['name'] ?? 'MyApp'
            )
        );
    }
}