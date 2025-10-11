<?php

declare(strict_types=1);

/**
 * @return array<int, array{key: string, label: string, href: string}>
 */
function getNavigationItems(): array
{
    return [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'index.php'],
        ['key' => 'accounts', 'label' => 'Accounts (CRM)', 'href' => '#'],
        ['key' => 'reports', 'label' => 'Reports', 'href' => 'index.php'],
        ['key' => 'billing', 'label' => 'Billing', 'href' => '#'],
        ['key' => 'suppliers', 'label' => 'Suppliers', 'href' => '#'],
        ['key' => 'tasks', 'label' => 'Tasks', 'href' => '#'],
        ['key' => 'settings', 'label' => 'Settings', 'href' => '#'],
    ];
}

function renderSidebar(string $activeNav = ''): void
{
    $items = getNavigationItems();

    echo '<aside class="sidebar">';
    echo '<nav class="sidebar__nav">';
    echo '<h2 class="sidebar__heading">Navigation</h2>';
    echo '<ul class="sidebar__list">';

    foreach ($items as $item) {
        $isActive = $activeNav === $item['key'] ? ' sidebar__link--active' : '';

        printf(
            '<li><a class="sidebar__link%s" href="%s">%s</a></li>',
            $isActive,
            htmlspecialchars($item['href'], ENT_QUOTES),
            htmlspecialchars($item['label'])
        );
    }

    echo '</ul>';
    echo '</nav>';
    echo '</aside>';
}
