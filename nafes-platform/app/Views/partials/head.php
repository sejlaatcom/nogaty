<?php
$primary = preg_match('/^#[0-9a-fA-F]{6}$/', setting('color_primary')) ? setting('color_primary') : '#0b5394';
$accent  = preg_match('/^#[0-9a-fA-F]{6}$/', setting('color_accent')) ? setting('color_accent') : '#0ea5b7';
[$r, $g, $b] = sscanf($primary, '#%02x%02x%02x');
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(rtrim(url('/'), '/')) ?>">
<title><?= e(($title ?? '') !== '' ? $title . ' — ' : '') ?><?= e(setting('platform_title')) ?></title>
<link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.rtl.min.css') ?>">
<link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style>:root{--brand:<?= e($primary) ?>;--accent:<?= e($accent) ?>;--bs-primary-rgb:<?= (int) $r ?>,<?= (int) $g ?>,<?= (int) $b ?>;--brand-dark:color-mix(in srgb,<?= e($primary) ?> 70%,#000)}</style>
<link rel="icon" href="<?= setting('logo') !== '' ? e(url('/media/logo')) : 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%230b5394%22/%3E%3Ctext x=%2250%22 y=%2268%22 font-size=%2256%22 text-anchor=%22middle%22 fill=%22white%22%3E%D9%86%3C/text%3E%3C/svg%3E' ?>">
