<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function dashboardSummary(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    (SELECT COUNT(*) FROM assignments) AS total_tugas,
    (SELECT COUNT(*) FROM field_results) AS total_hasil,
    (SELECT COUNT(*) FROM field_results WHERE gps_valid = 1) AS total_gps_valid,
    (SELECT COUNT(*) FROM kpi_results) AS total_kpi
SQL;

    return $pdo->query($sql)->fetch() ?: [
        'total_tugas' => 0,
        'total_hasil' => 0,
        'total_gps_valid' => 0,
        'total_kpi' => 0,
    ];
}

function monitoringRows(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    a.id AS assignment_id,
    p.name AS petugas,
    w.name AS wajib_pajak,
    a.target_amount,
    COALESCE(fr.realisasi_amount, 0) AS realisasi_amount,
    fr.latitude,
    fr.longitude,
    fr.submitted_at,
    COALESCE(kr.final_score, 0) AS final_score,
    COALESCE(kr.category, '-') AS kpi_category
FROM assignments a
INNER JOIN users p ON p.id = a.petugas_id
INNER JOIN taxpayers w ON w.id = a.taxpayer_id
LEFT JOIN field_results fr ON fr.assignment_id = a.id
LEFT JOIN kpi_results kr ON kr.assignment_id = a.id
ORDER BY COALESCE(fr.submitted_at, a.created_at) DESC
LIMIT 20
SQL;

    return $pdo->query($sql)->fetchAll() ?: [];
}

function rankingRows(PDO $pdo): array
{
    $sql = <<<'SQL'
SELECT
    u.name AS petugas,
    ROUND(AVG(kr.final_score), 2) AS avg_kpi,
    COUNT(kr.id) AS total_penilaian
FROM kpi_results kr
INNER JOIN assignments a ON a.id = kr.assignment_id
INNER JOIN users u ON u.id = a.petugas_id
GROUP BY u.id, u.name
ORDER BY avg_kpi DESC
LIMIT 10
SQL;

    return $pdo->query($sql)->fetchAll() ?: [];
}
