<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>Admin</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?= h(site_url('public/favicon/apple-touch-icon.png')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= h(site_url('public/favicon/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= h(site_url('public/favicon/favicon-16x16.png')) ?>">
<link rel="manifest" href="<?= h(site_url('public/favicon/site.webmanifest')) ?>">
<link rel="stylesheet" href="<?= h(site_url('public/css/admin.css')) ?>">
<script src="<?= h(site_url('public/js/admin.js')) ?>" defer></script>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-top-row">
      <p class="sidebar-title">🤖 Bot Admin</p>
      <a href="<?= h(admin_url('logout')) ?>" class="sidebar-logout-mobile">Logout</a>
    </div>
    <nav>
      <a href="<?= h(admin_url()) ?>">Bots</a>
      <a href="<?= h(admin_url('logs')) ?>">Logs</a>
      <a href="<?= h(admin_url('settings')) ?>">Settings</a>
      <hr class="hr-divider">
      <a href="<?= h(site_url()) ?>">← Public site</a>
      <a href="<?= h(admin_url('logout')) ?>" class="sidebar-logout-desktop">Logout</a>
    </nav>
  </aside>
  <main class="main<?= isset($extraMainClass) ? ' ' . h($extraMainClass) : '' ?>">

