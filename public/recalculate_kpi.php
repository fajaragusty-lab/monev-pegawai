<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/kpi.php';

$pdo = db();

$weightsStmt = $pdo->query('SELECT component_key, weight FROM kpi_weights');
$weights = [];
foreach ($weightsStmt->fetchAll() as $weight) {
    $weights[$weight['component_key']] = (float) $weight['weight'];
}

$sql = <<<'SQL'
SELECT
    a.id AS assignment_id,
    a.target_amount,
    fr.realisasi_amount,
    fr.penagihan_berhasil,
    fr.verval_complete,
    fr.gps_valid,
    fr.on_time,
    fr.document_completeness
FROM assignments a
INNER JOIN field_results fr ON fr.assignment_id = a.id
SQL;

$rows = $pdo->query($sql)->fetchAll();
$updated = 0;

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO kpi_results (
            assignment_id,
            target_achievement,
            collection_success,
            verification_rate,
            gps_validity,
            timeliness,
            document_completeness,
            final_score,
            category,
            calculated_at
        ) VALUES (
            :assignment_id,
            :target_achievement,
            :collection_success,
            :verification_rate,
            :gps_validity,
            :timeliness,
            :document_completeness,
            :final_score,
            :category,
            :calculated_at
        )
        ON DUPLICATE KEY UPDATE
            target_achievement = VALUES(target_achievement),
            collection_success = VALUES(collection_success),
            verification_rate = VALUES(verification_rate),
            gps_validity = VALUES(gps_validity),
            timeliness = VALUES(timeliness),
            document_completeness = VALUES(document_completeness),
            final_score = VALUES(final_score),
            category = VALUES(category),
            calculated_at = VALUES(calculated_at)'
    );

    foreach ($rows as $row) {
        $components = calculateKpiComponents($row);
        $finalScore = weightedKpiScore($components, $weights);
        $category = kpiCategory($finalScore);

        $stmt->execute([
            'assignment_id' => (int) $row['assignment_id'],
            'target_achievement' => $components['target_achievement'],
            'collection_success' => $components['collection_success'],
            'verification_rate' => $components['verification_rate'],
            'gps_validity' => $components['gps_validity'],
            'timeliness' => $components['timeliness'],
            'document_completeness' => $components['document_completeness'],
            'final_score' => $finalScore,
            'category' => $category,
            'calculated_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
        ]);

        $updated++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo 'Gagal rekalkulasi KPI: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekalkulasi KPI</title>
</head>
<body>
    <p>Rekalkulasi KPI selesai. Total data diperbarui: <strong><?= $updated ?></strong>.</p>
    <p><a href="/public/index.php">Kembali ke dashboard</a></p>
</body>
</html>
