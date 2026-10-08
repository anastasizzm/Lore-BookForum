<?php
/** @var object $view  — объект представления с методом fullurl() */
/** @var string $token — токен из письма */
$verifyUrl = $verifyUrl ?? '';
$logoUrl = $view->asset('img/logo.png');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Подтвердите email — Lore BookForum</title>
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
              Подтвердите ваш email
            </td>
          </tr>

          <!-- Приветствие -->
          <tr>
            <td style="padding:16px 32px 0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;color:#2F405D;">
              Здравствуйте!<br><br>
              Добро пожаловать в Lore BookForum. Чтобы завершить регистрацию,
              подтвердите адрес электронной почты, нажав на кнопку ниже.
            </td>
          </tr>

          <!-- Кнопка -->
          <tr>
            <td align="center" style="padding:28px 32px;">
              <a href="<?= $verifyUrl ?>" target="_blank"
                 style="display:inline-block;background-color:#5876A6;color:#FFFFFF;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">
                Verify email
              </a>
            </td>
          </tr>

          <!-- Альтернативная ссылка -->
          <tr>
            <td style="padding:0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6F84A5;">
              Если кнопка не работает, скопируйте ссылку и вставьте её в адресную строку браузера:
              <br>
              <a href="<?= $verifyUrl ?>" target="_blank" style="color:#5876A6;word-break:break-all;"><?= $view->e($verifyUrl) ?></a>
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
              С уважением,<br>
              <strong style="color:#29466F;">Lore BookForum</strong>
            </td>
          </tr>
        </table>

        <!-- Футер вне карточки -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
          <tr>
            <td align="center" style="padding:16px 16px 0 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6F84A5;">
              Вы получили это письмо, потому что этот адрес был указан при регистрации на Lore BookForum.
              Если это были не вы — просто проигнорируйте письмо.
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>
