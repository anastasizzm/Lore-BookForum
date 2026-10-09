<?php
/**
 * avatar-presets — единая карта пресетов аватара: id => [icon, bg].
 *
 * В БД (users.avatar) по-прежнему хранится только id ('cat', 'fox'...)
 * или 'default' (= показывать инициалы), поэтому миграция не нужна.
 * Отсюда читают avatar.php (рендер), profile-edit.php (пикер),
 * sidebar.php (иконка профиля) и валидатор на бэке (array_keys).
 *
 * icon — эмодзи, bg — цвет фона (hex, подставляется в --avatar-bg).
 *
 * Подключается через require (не $view->include).
 *
 * @return array<string, array{icon: string, bg: string}>
 */
return [
    'cat'   => ['icon' => '🐱', 'bg' => '#FFE1C9'],
    'dog'   => ['icon' => '🐶', 'bg' => '#F3DFC4'],
    'fox'   => ['icon' => '🦊', 'bg' => '#FFD2B0'],
    'owl'   => ['icon' => '🦉', 'bg' => '#E4D3C3'],
    'robot' => ['icon' => '🤖', 'bg' => '#D7E2F1'],
    'star'  => ['icon' => '⭐', 'bg' => '#FFF2B8'],
    'book'  => ['icon' => '📚', 'bg' => '#DCD4F0'],
];