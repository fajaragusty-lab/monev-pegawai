<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/dashboard.php';

$pdo = db();
$summary = dashboardSummary($pdo);
$rows = monitoringRows($pdo);
$ranking = rankingRows($pdo);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .card { border: 1px solid #ddd; padding: 12px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .muted { color: #666; }
        .topbar { margin-bottom: 16px; display: flex; gap: 12px; }
    </style>
</head>
<body>
    <h1>Dashboard Monitoring</h1>
    <div class="topbar">
        <a href="/public/input_hasil.php">Input Hasil Petugas</a>
        <a href="/public/recalculate_kpi.php">Rekalkulasi KPI</a>
    </div>

    <div class="grid">
        <div class="card"><strong>Total Tugas</strong><br><?= (int) $summary['total_tugas'] ?></div>
        <div class="card"><strong>Total Hasil</strong><br><?= (int) $summary['total_hasil'] ?></div>
        <div class="card"><strong>GPS Valid</strong><br><?= (int) $summary['total_gps_valid'] ?></div>
        <div class="card"><strong>Data KPI</strong><br><?= (int) $summary['total_kpi'] ?></div>
    </div>

    <h2>Data Monitoring Terbaru</h2>
    <?php if (!$rows): ?>
        <p class="muted">Belum ada data monitoring. Import dummy seed atau lakukan input hasil petugas terlebih dahulu.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID Tugas</th>
                    <th>Petugas</th>
                    <th>Wajib Pajak</th>
                    <th>Target</th>
                    <th>Realisasi</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>KPI</th>
                    <th>Kategori</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= (int) $row['assignment_id'] ?></td>
                        <td><?= htmlspecialchars((string) $row['petugas']) ?></td>
                        <td><?= htmlspecialchars((string) $row['wajib_pajak']) ?></td>
                        <td><?= number_format((float) $row['target_amount'], 0, ',', '.') ?></td>
                        <td><?= number_format((float) $row['realisasi_amount'], 0, ',', '.') ?></td>
                        <td><?= $row['latitude'] !== null ? htmlspecialchars((string) $row['latitude']) : '-' ?></td>
                        <td><?= $row['longitude'] !== null ? htmlspecialchars((string) $row['longitude']) : '-' ?></td>
                        <td><?= number_format((float) $row['final_score'], 2) ?></td>
                        <td><?= htmlspecialchars((string) $row['kpi_category']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Ranking KPI Petugas</h2>
    <?php if (!$ranking): ?>
        <p class="muted">Belum ada hasil KPI untuk ditampilkan.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Petugas</th>
                    <th>Rata-rata KPI</th>
                    <th>Total Penilaian</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ranking as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['petugas']) ?></td>
                        <td><?= number_format((float) $row['avg_kpi'], 2) ?></td>
                        <td><?= (int) $row['total_penilaian'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
