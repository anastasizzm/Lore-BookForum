<?php
/**
 * Entry-точка главной страницы «For you».
 * Собирает контент и оборачивает его в общий layout.
 */

$active = 'for-you';
$pageTitle = 'For you';

/* --- Собираем поиск как pageActions --- */
ob_start();
$type = 'search';
$name = 'q';
$placeholder = 'Search';
$value = '';
include __DIR__ . '/../src/components/input.php';
$pageActions = ob_get_clean();

/* --- Собираем основной контент страницы --- */
ob_start();

// Верхняя часть страницы: заголовок + поиск
include __DIR__ . '/../src/partials/page-header.php';

// Лента постов
include __DIR__ . '/../src/views/pages/feed/feed-list.php';

$content = ob_get_clean();

/* --- Отдаём всё в layout --- */
include __DIR__ . '/../src/layout.php';