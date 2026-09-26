<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Email;
use App\Exceptions\MailException;

interface Mailer
{
    /** @throws MailException */
    public function send(Email $email): void;
}