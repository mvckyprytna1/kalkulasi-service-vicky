<?php
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/service_workflow.php';
require_login();
$config = app_config();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        try {
            $action = clean_text($_POST['action'] ?? 'save', 40);
            if ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = db()->prepare('DELETE FROM whatsapp_templates WHERE id = ?');
                $stmt->execute([$id]);
                $success = 'Template dihapus.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($_POST['template_key'] ?? ''));
                $title = clean_text($_POST['title'] ?? '', 160);
                $body = trim((string)($_POST['body'] ?? ''));
                $sort = (int)n($_POST['sort_order'] ?? 99);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($key === '' || $title === '' || $body === '') {
                    throw new RuntimeException('Kode, judul, dan isi template wajib diisi.');
                }
                if ($id > 0) {
                    $stmt = db()->prepare('UPDATE whatsapp_templates SET template_key=?, title=?, body=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?');
                    $stmt->execute([$key, $title, $body, $active, $sort, $id]);
                } else {
                    $stmt = db()->prepare('INSERT INTO whatsapp_templates (template_key, title, body, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([$key, $title, $body, $active, $sort]);
                }
                $success = 'Template disimpan.';
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan template: ' . $e->getMessage();
        }
    }
}
$templates = db()->query('SELECT * FROM whatsapp_templates ORDER BY sort_order ASC, id ASC')->fetchAll();
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Template WhatsApp - <?= e($config['app']['name']) ?></title><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><link rel="stylesheet" href="assets/css/style.css"></head>
<body><?php include __DIR__ . '/partials_nav.php'; ?>
<main class="history-page"><div class="container"><div class="panel wide-panel"><div class="panel-head"><div><span class="section-kicker">Template</span><h1 class="history-title">WhatsApp</h1></div><a class="btn btn-soft" href="service_orders.php">Job Ticket</a></div>
<?php if($success): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?><?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<h3>Tambah Template</h3><form method="post" class="settings-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input name="template_key" placeholder="kode_unik"><input name="title" placeholder="Judul template"><input name="sort_order" type="number" value="99"><label class="inline-check"><input type="checkbox" name="is_active" checked> Aktif</label><textarea name="body" rows="5" placeholder="Halo Kak {nama}, ticket {ticket}..."></textarea><button class="btn" type="submit">Tambah Template</button></form>
<h3>Data Template</h3><?php foreach($templates as $tpl): ?><form method="post" class="settings-row template-row"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($tpl['id']) ?>"><label class="tiny-check"><input type="checkbox" name="is_active" <?= $tpl['is_active']?'checked':'' ?>></label><input name="template_key" value="<?= e($tpl['template_key']) ?>"><input name="title" value="<?= e($tpl['title']) ?>"><textarea name="body" rows="3"><?= e($tpl['body']) ?></textarea><input name="sort_order" type="number" value="<?= e($tpl['sort_order']) ?>"><button class="mini-btn" type="submit">Simpan</button><button class="danger-btn small" name="action" value="delete" onclick="return confirm('Hapus template ini?')">Hapus</button></form><?php endforeach; ?>
<div class="detail-section"><h3>Placeholder</h3><p><code>{nama}</code> <code>{ticket}</code> <code>{perangkat}</code> <code>{layanan}</code> <code>{keluhan}</code> <code>{status}</code> <code>{total}</code> <code>{dp}</code> <code>{dibayar}</code> <code>{sisa}</code> <code>{garansi_sampai}</code> <code>{brand}</code></p></div>
</div></div></main><script>document.querySelector('#navToggle')?.addEventListener('click',()=>document.querySelector('#navLinks')?.classList.toggle('open'));</script></body></html>
