<?php
declare(strict_types=1);

namespace App\Services;

use App\Lib\Settings;
use App\Models\Email;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use App\Services\Mailer;
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
                0,
                $e,
            );
        } finally {
            $mailer->smtpClose();
        }
    }

    private function buildMailer(): PHPMailer
    {
        $s = $this->settings;
        $m = new PHPMailer(exceptions: true);

        $m->isSMTP();
        $m->Host       = $s->mailHost;
        $m->Port       = $s->mailPort;
        $m->SMTPAuth   = $s->mailUsername !== '';
        $m->Username   = $s->mailUsername;
        $m->Password   = $s->mailPassword;
        $m->SMTPSecure = match ($s->mailEncryption) {
            'tls'  => PHPMailer::ENCRYPTION_STARTTLS,
            'ssl'  => PHPMailer::ENCRYPTION_SMTPS,
            ''     => '',
            default => throw new MailException("Unknown encryption: {$s->mailEncryption}"),
        };
        $m->CharSet = 'UTF-8';

        $m->setFrom($s->mailFromAddress, $s->mailFromName);
        $m->Timeout = 10;   // секунд — не даём SMTP зависать

        return $m;
    }
}