<?php

declare(strict_types=1);

namespace WPP\Admin;

use WPP\Foundation\Hooks\HookProvider;

/**
 * Registers the top-level WP Performance admin menu page.
 * The page body is a single mount node for the React admin app.
 */
final class AdminMenu extends HookProvider
{
    protected function actions(): array
    {
        return ['admin_menu' => 'registerMenu'];
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('WP Performance', 'wpp'),
            __('WP Performance', 'wpp'),
            'manage_options',
            WPP_SLUG,
            [$this, 'render'],
            $this->icon(),
            80
        );
    }

    /**
     * The plugin mark as a menu icon. Single colour in the default menu grey:
     * WordPress only varies opacity between states, so the fill is baked in.
     */
    private function icon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256">'
            . '<path fill="#a7aaad" d="M159.7 47 L73.4 147.4 L112.2 147.4 L99.8 209 L182.6 112.2 L143.8 112.2 Z"/>'
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function render(): void
    {
        echo '<div class="wrap wpp-wrap"><div id="wpp-admin-root"></div></div>';
    }
}
