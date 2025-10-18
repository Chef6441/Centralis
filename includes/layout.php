<?php

declare(strict_types=1);

if (!function_exists('renderHeader')) {
    /**
     * Render the shared application header.
     *
     * @param array<int, array<string, mixed>|string> $subNavLinks Links to include in the sub navigation bar.
     */
    function renderHeader(array $subNavLinks = []): void
    {
        $mainNavLinks = [
            ['href' => 'index.php', 'label' => 'Dashboard'],
            ['href' => '#', 'label' => 'Accounts'],
            ['href' => 'reports.php', 'label' => 'Reports'],
            ['href' => '#', 'label' => 'Billing'],
            ['href' => '#', 'label' => 'Tasks'],
            ['href' => '#', 'label' => 'Settings'],
        ];

        $subNavHtmlParts = [];
        foreach ($subNavLinks as $link) {
            if (is_string($link)) {
                $subNavHtmlParts[] = $link;
                continue;
            }

            if (!is_array($link) || !isset($link['label'])) {
                continue;
            }

            $href = isset($link['href']) ? (string) $link['href'] : '#';
            $label = (string) $link['label'];
            $attributes = '';

            if (!empty($link['attributes']) && is_array($link['attributes'])) {
                foreach ($link['attributes'] as $attribute => $value) {
                    $attributes .= ' ' . htmlspecialchars((string) $attribute, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
                }
            }

            $subNavHtmlParts[] = sprintf(
                '<a href="%s"%s>%s</a>',
                htmlspecialchars($href, ENT_QUOTES),
                $attributes,
                htmlspecialchars($label, ENT_QUOTES)
            );
        }

        ?>
<header>
    <div>
        <h1>Centralis</h1>
    </div>
    <nav class="navbar-main">
        <?php foreach ($mainNavLinks as $index => $link): ?>
            <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES) ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES) ?></a><?= $index < count($mainNavLinks) - 1 ? ' |' : '' ?>
        <?php endforeach; ?>
    </nav>
    <br>
    <nav class="navbar-sub">
        <?= $subNavHtmlParts ? implode(' | ', $subNavHtmlParts) : '' ?>
    </nav>
</header>
<?php
    }
}
