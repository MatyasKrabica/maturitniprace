<?php

// Jazykový helper – výběr locale (cs/en)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getLocale(): string
{
    $supported = ['cs', 'en'];

    $fromQuery = $_GET['lang'] ?? null;
    $fromCookie = $_COOKIE['lang'] ?? null;
    $fromSession = $_SESSION['lang'] ?? null;

    $candidate = null;
    if (is_string($fromQuery) && $fromQuery !== '') {
        $candidate = $fromQuery;
    } elseif (is_string($fromSession) && $fromSession !== '') {
        $candidate = $fromSession;
    } elseif (is_string($fromCookie) && $fromCookie !== '') {
        $candidate = $fromCookie;
    }

    if (!is_string($candidate)) {
        $candidate = 'cs';
    }

    $candidate = strtolower(substr($candidate, 0, 2));
    if (!in_array($candidate, $supported, true)) {
        $candidate = 'cs';
    }

    if (is_string($fromQuery) && $fromQuery !== '') {
        setcookie('lang', $candidate, time() + 60 * 60 * 24 * 365, '/');
        $_SESSION['lang'] = $candidate;
    } elseif (!isset($_SESSION['lang'])) {
        $_SESSION['lang'] = $candidate;
    }

    return (string)$_SESSION['lang'];
}

function t(string $key, string $fallback = ''): string
{
    $locale = getLocale();
    $file = __DIR__ . '/../../locales/' . $locale . '.php';

    if (!file_exists($file)) {
        return $fallback;
    }

    $messages = include $file;
    if (!is_array($messages)) {
        return $fallback;
    }

    return array_key_exists($key, $messages) ? (string)$messages[$key] : $fallback;
}

