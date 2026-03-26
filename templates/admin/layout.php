<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — ' : '' ?>Admin</title>
<link rel="stylesheet" href="<?= h(site_url('public/css/admin.css')) ?>">
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

