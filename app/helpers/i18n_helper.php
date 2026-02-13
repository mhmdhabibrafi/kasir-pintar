<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_helper.php';

function i18n_supported_langs(): array
{
    return ['en'];
}

function i18n_default_lang(): string
{
    return 'en';
}

function i18n_is_supported(string $lang): bool
{
    return in_array($lang, i18n_supported_langs(), true);
}

function i18n_set_lang(string $lang): string
{
    $lang = 'en';
    start_session();
    $_SESSION['app_lang'] = $lang;
    return $lang;
}

function i18n_current_lang(): string
{
    start_session();
    $_SESSION['app_lang'] = 'en';
    return 'en';
}

function i18n_dict(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $file = __DIR__ . '/../lang/' . $lang . '.php';
    if (!is_file($file)) {
        $cache[$lang] = [];
        return $cache[$lang];
    }

    $dict = require $file;
    $cache[$lang] = is_array($dict) ? $dict : [];
    return $cache[$lang];
}

function __(string $key, array $replace = []): string
{
    $lang = i18n_current_lang();
    $dict = i18n_dict($lang);
    $text = isset($dict[$key]) ? (string) $dict[$key] : $key;

    if (!empty($replace)) {
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
    }

    return $text;
}

function lang_switch_url(string $targetLang): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($requestUri);
    $path = (string) ($parts['path'] ?? '/');
    return $path;
}
