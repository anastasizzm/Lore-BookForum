<?php
$active       = 'library';
$pageTitle    = 'Library';
$pageSubtitle = 'Explore the complete library catalog.';

ob_start();
$type = 'search';
$name = 'q';
$placeholder = 'Search books';
$value = '';
include __DIR__ . '/../src/components/input.php';
?>
<button type="button"
        class="btn-icon filter-toggle"
        data-filter-toggle
        aria-label="Фильтры">
  <span>☰</span>
</button>
<?php
$pageActions = ob_get_clean();

ob_start();
include __DIR__ . '/../src/partials/page-header.php';
include __DIR__ . '/../src/views/pages/library/library-filters.php';
include __DIR__ . '/../src/views/pages/library/library-list.php';
$content = ob_get_clean();

include __DIR__ . '/../src/layout.php';