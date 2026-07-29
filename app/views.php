<?php
function nav_links(): array
{
    $links = [
        ['/dashboard', 'Dashboard'],
        ['/master/employees', 'Master Data'],
        ['/assignments', 'Penugasan'],
        ['/field-tasks', 'Pelaksanaan'],
        ['/verification', 'Verifikasi'],
        ['/kpi', 'Perhitungan KPI'],
        ['/rankings', 'Ranking Pegawai'],
        ['/incentives', 'Insentif'],
        ['/reports', 'Laporan'],
    ];

    if (!is_admin()) {
        return [
            ['/dashboard', 'Dashboard'],
            ['/assignments', 'Tugas Saya'],
            ['/field-tasks', 'Pelaksanaan'],
            ['/reports', 'Laporan'],
        ];
    }

    return $links;
}

function render_page(string $title, callable $content, array $options = []): void
{
    $showNav = $options['showNav'] ?? true;
    $pageTitle = app_config('app.name', 'Aplikasi');
    $flashes = pull_flashes();
    $currentPath = current_path();

    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> - <?= e($pageTitle) ?></title>
        <link rel="stylesheet" href="<?= e(base_url('public/assets/style.css')) ?>">
    </head>
    <body>
    <?php if ($showNav): ?>
        <div class="layout">
            <aside class="sidebar">
                <h1><?= e($pageTitle) ?></h1>
                <p class="muted" style="color:#9ca3af; margin-top:0;">Masuk sebagai <?= e(current_user()['name'] ?? '-') ?> (<?= e(current_user()['role'] ?? '-') ?>)</p>
                <?php foreach (nav_links() as [$path, $label]): ?>
                    <a href="<?= e(route_url($path)) ?>" class="<?= (($path === '/master/employees' && str_starts_with($currentPath, '/master')) || ($path !== '/master/employees' && str_starts_with($currentPath, $path) && $path !== '/dashboard') || $currentPath === $path) ? 'active' : '' ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
                <a href="<?= e(route_url('/logout')) ?>">Logout</a>
            </aside>
            <main class="main">
                <div class="topbar">
                    <div>
                        <h2 style="margin:0;"><?= e($title) ?></h2>
                        <small class="muted"><?= e(date('d M Y H:i')) ?> WIB</small>
                    </div>
                </div>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endforeach; ?>
                <?php $content(); ?>
            </main>
        </div>
    <?php else: ?>
        <div class="login-wrap">
            <div class="card">
                <h1><?= e($pageTitle) ?></h1>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
                <?php endforeach; ?>
                <?php $content(); ?>
            </div>
        </div>
    <?php endif; ?>
    </body>
    </html>
    <?php
}

function render_master_links(AppService $service): void
{
    echo '<div class="card"><div class="list-inline">';
    foreach (app_config('master_order', []) as $slug) {
        $module = $service->module($slug);
        echo '<a class="btn light" href="' . e(route_url('/master/' . $slug)) . '">' . e($module['title']) . '</a>';
    }
    echo '</div></div>';
}

function render_form_fields(array $fields, array $values, AppService $service, bool $isEdit = false): void
{
    echo '<div class="form-grid">';
    foreach ($fields as $field => $meta) {
        $value = $values[$field] ?? ($meta['default'] ?? '');
        echo '<div class="form-group">';
        echo '<label for="' . e($field) . '">' . e($meta['label']) . '</label>';
        if ($meta['type'] === 'textarea') {
            echo '<textarea id="' . e($field) . '" name="' . e($field) . '">' . e((string) $value) . '</textarea>';
        } elseif ($meta['type'] === 'select') {
            $options = $meta['options'] ?? $service->selectOptions($meta['source'], $meta['source_label'] ?? 'name');
            echo '<select id="' . e($field) . '" name="' . e($field) . '">';
            echo '<option value="">-- pilih --</option>';
            foreach ($options as $optionValue => $label) {
                $selected = (string) $optionValue === (string) $value ? 'selected' : '';
                echo '<option value="' . e((string) $optionValue) . '" ' . $selected . '>' . e($label) . '</option>';
            }
            echo '</select>';
        } else {
            $type = $meta['type'] === 'password' ? 'password' : ($meta['type'] === 'number' ? 'number' : ($meta['type'] === 'date' ? 'date' : 'text'));
            $step = isset($meta['step']) ? ' step="' . e((string) $meta['step']) . '"' : '';
            $placeholder = $meta['type'] === 'password' && $isEdit ? ' placeholder="Kosongkan jika tidak diubah"' : '';
            echo '<input type="' . e($type) . '" id="' . e($field) . '" name="' . e($field) . '" value="' . ($type === 'password' ? '' : e((string) $value)) . '"' . $step . $placeholder . '>';
        }
        echo '</div>';
    }
    echo '</div>';
}

function status_badge(string $status): string
{
    return '<span class="badge status-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function report_bar(float $value, float $max): string
{
    $percent = $max > 0 ? min(100, ($value / $max) * 100) : 0;
    return '<div class="kpi-bar"><span style="width:' . e((string) $percent) . '%"></span></div>';
}

function export_csv(array $payload): never
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9\-_]+/i', '_', $payload['title']) . '.csv"');

    $out = fopen('php://output', 'wb');
    fputcsv($out, $payload['headers']);
    foreach ($payload['rows'] as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function export_simple_pdf(array $payload): never
{
    $lines = array_merge([$payload['title'], ''], [implode(' | ', $payload['headers'])]);
    foreach ($payload['rows'] as $row) {
        $lines[] = implode(' | ', array_map(fn ($cell) => str_replace(['(', ')'], ['[', ']'], (string) $cell), $row));
    }

    $pdfLines = [];
    $y = 800;
    foreach ($lines as $line) {
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_substr($line, 0, 110));
        $pdfLines[] = "BT /F1 10 Tf 40 {$y} Td ({$clean}) Tj ET";
        $y -= 14;
        if ($y < 60) {
            break;
        }
    }
    $stream = implode("\n", $pdfLines);
    $length = strlen($stream);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    $objects = [
        '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
        '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
        '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
        "4 0 obj << /Length {$length} >> stream\n{$stream}\nendstream endobj",
        '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
    ];

    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object . "\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
    }
    $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9\-_]+/i', '_', $payload['title']) . '.pdf"');
    echo $pdf;
    exit;
}
