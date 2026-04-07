<?php
/**
 * Global helper functions — auto-loaded by Composer.
 */

if (!function_exists('asset')) {
    /**
     * Generate a URL for a public asset (CSS, JS, images).
     * Works whether the app is at root or in a subdirectory.
     *
     * Usage: asset('css/app.css')  → /system-ac/css/app.css
     */
    function asset(string $path): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        $path = ltrim($path, '/');
        return $base . '/' . $path;
    }
}

if (!function_exists('url')) {
    /**
     * Generate a URL for an internal route.
     *
     * Usage: url('/invoices')  → https://dev-env.tabsyst.com/system-ac/invoices
     */
    function url(string $path = ''): string
    {
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('base_path')) {
    /**
     * Return the subdirectory base path (no trailing slash).
     * e.g. '/system-ac'  or '' for root installs.
     */
    function base_path(): string
    {
        $url = rtrim($_ENV['APP_URL'] ?? '', '/');
        $parsed = parse_url($url, PHP_URL_PATH);
        return rtrim($parsed ?? '', '/');
    }
}

if (!function_exists('redirect_to')) {
    /**
     * Issue a Location redirect using APP_URL-aware path.
     */
    function redirect_to(string $path, int $code = 302): void
    {
        header('Location: ' . url($path), true, $code);
        exit;
    }
}
