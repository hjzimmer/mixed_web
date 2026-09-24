<?php

declare(strict_types=1);

/**
 * Escapes text for safe HTML output.
 *
 * @param string $value Text to escape.
 * @return string Escaped text.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Renders the shared page header and navigation.
 *
 * @param string $title Page title.
 * @param string $teamName Current team name.
 * @param string $page Active page key.
 * @return void
 */
function renderHeader(string $title, string $teamName, string $page): void
{
    ?><!doctype html>
    <html lang="de">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> | <?= e($teamName) ?></title>
        <link rel="stylesheet" href="public/styles.css">
    </head>
    <body>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="?page=dashboard">
                <span class="brand-mark">V</span>
                <span><strong><?= e($teamName) ?></strong><small>Spielverwaltung</small></span>
            </a>
            <nav class="main-nav" aria-label="Hauptnavigation">
                <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">Übersicht</a>
                <a class="<?= $page === 'team' ? 'active' : '' ?>" href="?page=team">Mannschaft</a>
                <a class="<?= $page === 'matchdays' ? 'active' : '' ?>" href="?page=matchdays">Spieltage</a>
                <a class="<?= $page === 'stats' ? 'active' : '' ?>" href="?page=stats">Statistik</a>
            </nav>
        </div>
    </header>
    <main class="shell page-content">
    <?php if (isset($_GET['saved'])): ?><div class="notice success" role="status">Änderungen gespeichert.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="notice error" role="alert"><?= e((string) $_GET['error']) ?></div><?php endif; ?>
    <?php
}

/**
 * Renders the shared page footer.
 *
 * @return void
 */
function renderFooter(): void
{
    ?></main>
    <footer class="site-footer"><div class="shell">Mannschaftsverwaltung · JSON lokal gespeichert</div></footer>
    <script src="public/app.js"></script>
    </body>
    </html><?php
}

/**
 * Renders a page heading with an optional action.
 *
 * @param string $eyebrow Small context label.
 * @param string $heading Main heading.
 * @param string $description Supporting text.
 * @param string $actionHtml Optional action markup.
 * @return void
 */
function renderPageHeading(string $eyebrow, string $heading, string $description = '', string $actionHtml = ''): void
{
    echo '<div class="page-heading"><div><p class="eyebrow">' . e($eyebrow) . '</p><h1>' . e($heading) . '</h1>';
    if ($description !== '') {
        echo '<p class="lede">' . e($description) . '</p>';
    }
    echo '</div>' . $actionHtml . '</div>';
}

/**
 * Renders a blank state message.
 *
 * @param string $title Blank state title.
 * @param string $text Blank state details.
 * @return void
 */
function renderEmptyState(string $title, string $text): void
{
    echo '<div class="empty-state"><span class="empty-icon">+</span><h2>' . e($title) . '</h2><p>' . e($text) . '</p></div>';
}