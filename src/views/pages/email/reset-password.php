<?php
/** @var object $view  — объект представления с методом fullurl() */
/** @var string $token — токен из письма */
$resetUrl = $view->fullUrl('auth/password-reset/' . $token);
$logoUrl = $view->fullUrl('assets/img/logo.png');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title>Сброс пароля — Lore BookForum</title>
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
              Сброс пароля
            </td>
          </tr>

          <!-- Текст -->
          <tr>
            <td style="padding:16px 32px 0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;color:#2F405D;">
              Вы запросили сброс пароля для вашего аккаунта на Lore BookForum.
              Нажмите на кнопку ниже, чтобы задать новый пароль.
            </td>
          </tr>

          <!-- Кнопка -->
          <tr>
            <td align="center" style="padding:28px 32px;">
              <a href="<?= htmlspecialchars($resetUrl) ?>" target="_blank"
                 style="display:inline-block;background-color:#5876A6;color:#FFFFFF;font-family:Inter,Arial,Helvetica,sans-serif;font-size:14px;line-height:20px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;">
                Reset password
              </a>
            </td>
          </tr>

          <!-- Альтернативная ссылка -->
          <tr>
            <td style="padding:0 32px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#6F84A5;">
              Если кнопка не работает, скопируйте ссылку и вставьте её в адресную строку браузера:
              <br>
              <a href="<?= htmlspecialchars($resetUrl) ?>" target="_blank" style="color:#5876A6;word-break:break-all;"><?= htmlspecialchars($resetUrl) ?></a>
            </td>
          </tr>

          <!-- Предупреждение -->
          <tr>
            <td style="padding:24px 32px 0 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                     style="background-color:#FDF6EA;border-left:4px solid #E9A23B;border-radius:8px;">
                <tr>
                  <td style="padding:12px 16px;font-family:Inter,Arial,Helvetica,sans-serif;font-size:12px;line-height:18px;color:#2F405D;">
                    Если вы не запрашивали сброс — проигнорируйте письмо. Ваш пароль останется прежним.
                  </td>
                </tr>
              </table>
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

      </td>
    </tr>
  </table>
</body>
</html>
