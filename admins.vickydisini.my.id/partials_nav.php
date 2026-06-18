<?php $cfg = app_config(); ?>
<header class="topbar">
    <div class="container nav">
        <a class="brand" href="calculator.php">
            <span class="brand-logo">SP</span>
            <span><strong><?= e($cfg['app']['name']) ?></strong><small><?= e($cfg['app']['tagline']) ?></small></span>
        </a>
        <button class="nav-toggle" type="button" id="navToggle" aria-label="Buka menu">☰</button>
        <nav class="nav-links" id="navLinks">
            <a href="calculator.php">Kalkulator</a>
            <a href="history.php">Riwayat</a>
            <a href="service_orders.php">Service</a>
            <a href="clients.php">Client</a>
            <a href="templates.php">Template</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>
