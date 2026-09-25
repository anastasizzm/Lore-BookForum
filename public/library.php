<?php
$active = 'library';
$pageTitle = 'Library';

// Поиск + сортировка как pageActions
ob_start();
$type = 'search';
$name = 'q';
$placeholder = 'Search';
$value = '';
include __DIR__ . '/../src/components/input.php';
$pageActions = ob_get_clean();

ob_start();
include __DIR__ . '/../src/partials/page-header.php';
include __DIR__ . '/../src/views/pages/library/library-list.php';
$content = ob_get_clean();

include __DIR__ . '/../src/layout.php';