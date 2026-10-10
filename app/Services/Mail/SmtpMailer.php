<?php
declare(strict_types=1);

namespace App\Services\Mail;

use App\Lib\Settings\Settings;

use App\Models\Email;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

use App\Services\Mail\Mailer;
use App\ErrorCodes;
use App\Exceptions\MailException;
use Throwable;

final class SmtpMailer implements Mailer
{
    public function __construct(private readonly Settings $settings) {}

    public function send(Email $email): void
    {
        $mailer = $this->buildMailer();

        try {
            $mailer->addAddress(...$email->to);

            foreach ($email->cc as $address) {
                $mailer->addCC($address);
            }
            foreach ($email->bcc as $address) {
                $mailer->addBCC($address);
            }

            foreach ($email->headers as $name => $value) {
                $mailer->addCustomHeader($name, $value);
            }

            $mailer->Subject = $email->subject;
            $mailer->isHTML($email->isHtml);
            $mailer->Body    = $email->isHtml
                ? $email->body
                : $email->body;                       // PHPMailer берёт AltBody для plain
            if (!$email->isHtml) {
                $mailer->Body    = '';
                $mailer->AltBody = $email->body;
            }

            $mailer->send();
        } catch (PHPMailerException $e) {
            throw new MailException(
                'Failed to send email: ' . $e->getMessage(),
                ErrorCodes::UNHANDLED_EX
            );
        } finally {
            $mailer->smtpClose();
        }
    }

    private function buildMailer(): PHPMailer
    {
        $s = $this->settings->mail;
        $m = new PHPMailer(exceptions: true);

        $m->isSMTP();
        $m->Host       = $s->host;
        $m->Port       = $s->port;
        $m->SMTPAuth   = $s->username !== '';
        $m->Username   = $s->username;
        $m->Password   = $s->password;
        $m->SMTPSecure = match ($s->encryption) {
            'tls'  => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl'  => PHPMailer::ENCRYPTION_SMTPS,
            ''     => '',
            default => throw new MailException("Unknown encryption: {$s->mailEncryption}", ErrorCodes::INVALID_ENCTYPTION),
        };
        $m->CharSet = 'UTF-8';

        $m->setFrom($s->from->address, $s->from->name);
        $m->Timeout = 10;

        return $m;
    }
}