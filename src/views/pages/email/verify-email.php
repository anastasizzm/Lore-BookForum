<?php
/** @var object $view  — объект представления с методом fullurl() */
/** @var string $token — токен из письма */
$verifyUrl = $verifyUrl ?? '';
$logoUrl = $view->fullAsset('img/logo.png');
$tr = static fn(string $key, array $p = []): string => $view->e($view->t($key, $p));
?>
<!DOCTYPE html>
<html lang="<?= $view->e($view->locale()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= $tr('common.email.verify_title') ?> — Lore BookForum</title>
</head>
<body style="margin:0;padding:0;background-color:#F3F6FB;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F3F6FB;">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="max-width:520px;background-color:#FFFFFF;border:1px solid #D6E0EF;border-radius:16px;">

          <!-- Лого -->
          <tr>
            <td align="center" style="padding:32px 32px 8px 32px;">
              <img src="<?= htmlspecialchars($logoUrl) ?>" width="64" height="62" alt="Lore BookForum"
                   style="display:block;border:0;outline:none;text-decoration:none;">
            </td>
          </tr>

          <!-- Заголовок -->
          <tr>
            <td align="center" style="padding:16px 32px 0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:24px;line-height:32px;font-weight:700;color:#29466F;">
              <?= $tr('common.email.verify_heading') ?>
            </td>
          </tr>

          <!-- Приветствие -->
          <tr>
            <td style="padding:16px 32px 0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;color:#2F405D;">
              <?= $tr('common.email.verify_hello') ?><br><br>
              <?= $tr('common.email.verify_text') ?>
            </td>
          </tr>

          <!-- Кнопка -->
          <tr>
            <td align="center" style="padding:28px 32px;">
              <a href="<?= $verifyUrl ?>" target="_blank"
                 style="display:inline-block;background-color:#5876A6;color:#FFFFFF;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">
                <?= $tr('common.email.verify_button') ?>
              </a>
            </td>
          </tr>

          <!-- Разделитель -->
          <tr>
            <td style="padding:24px 32px 0 32px;">
              <div style="border-top:1px solid #D6E0EF;font-size:0;line-height:0;">&nbsp;</div>
            </td>
          </tr>

          <!-- Подпись -->
          <tr>
            <td style="padding:16px 32px 32px 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;color:#2F405D;">
              <?= $tr('common.email.signature') ?><br>
              <strong style="color:#29466F;">Lore BookForum</strong>
            </td>
          </tr>
        </table>

        <!-- Футер вне карточки -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
          <tr>
            <td align="center" style="padding:16px 16px 0 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6F84A5;">
              <?= $tr('common.email.verify_footer') ?>
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>