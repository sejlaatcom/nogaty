<?php
/**
 * نقطة الدخول الوحيدة للنظام
 * نظام الأرشفة الإلكترونية للمدارس
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

// رؤوس أمان
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$route = (string) ($_GET['r'] ?? 'dashboard/index');
Router::dispatch($route);
