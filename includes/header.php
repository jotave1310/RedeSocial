<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

ensureSessionStarted();

$pageTitle = isset($pageTitle) && is_string($pageTitle) && trim($pageTitle) !== ''
    ? trim($pageTitle) . ' | ' . APP_NAME
    : APP_NAME;

$pageDescription = isset($pageDescription) && is_string($pageDescription) && trim($pageDescription) !== ''
    ? trim($pageDescription)
    : 'CARVASILVA - Rede social escolar com sistema de créditos fictícios.';

$bodyClass = isset($bodyClass) && is_string($bodyClass) ? trim($bodyClass) : '';
$pageCss = isset($pageCss) && is_array($pageCss) ? $pageCss : [];
$currentRoute = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$basePath = appBasePath();
if ($basePath !== '' && strncasecmp($currentRoute, $basePath, strlen($basePath)) === 0) {
    $currentRoute = substr($currentRoute, strlen($basePath));
    if ($currentRoute === '') {
        $currentRoute = '/';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#0D0D0D">
    <meta name="color-scheme" content="dark light">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($pageTitle) ?></title>

    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem("carvasilva_theme");
                const theme = savedTheme === "light" ? "light" : "dark";
                document.documentElement.setAttribute("data-theme", theme);
            } catch (_) {
                document.documentElement.setAttribute("data-theme", "dark");
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= e(appUrl('/assets/css/main.css')) ?>">

    <?php foreach ($pageCss as $stylesheet): ?>
        <?php if (is_string($stylesheet) && $stylesheet !== ''): ?>
            <?php $sheetHref = str_starts_with($stylesheet, '/') ? appUrl($stylesheet) : $stylesheet; ?>
            <link rel="stylesheet" href="<?= e($sheetHref) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>" data-route="<?= e($currentRoute) ?>" data-base-url="<?= e(appUrl('/')) ?>">
