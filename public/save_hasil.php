<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/kpi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/input_hasil.php?error=Metode tidak valid');
    exit;
}

function redirectError(string $message): void
{
    header('Location: /public/input_hasil.php?error=' . urlencode($message));
    exit;
}

function validCoordinate($value, float $min, float $max): bool
{
    if (!is_numeric($value)) {
        return false;
    }

    $num = (float) $value;
    return $num >= $min && $num <= $max;
}

$assignmentId = (int) ($_POST['assignment_id'] ?? 0);
$realisasi = (float) ($_POST['realisasi_amount'] ?? 0);
$penagihan = (float) ($_POST['penagihan_berhasil'] ?? 0);
$doc = (float) ($_POST['document_completeness'] ?? 0);
$vervalComplete = (int) ($_POST['verval_complete'] ?? 0);
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$accuracy = $_POST['accuracy'] ?? null;

if ($assignmentId <= 0) {
    redirectError('Tugas harus dipilih.');
}

if (!validCoordinate($latitude, -90, 90) || !validCoordinate($longitude, -180, 180)) {
    redirectError('Koordinat GPS tidak valid atau belum tersedia. Izinkan akses lokasi lalu coba lagi.');
}

if ($realisasi < 0 || $penagihan < 0 || $doc < 0 || $doc > 100) {
    redirectError('Nilai hasil lapangan tidak valid.');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $assignmentStmt = $pdo->prepare('SELECT id, target_amount, deadline_at FROM assignments WHERE id = :id LIMIT 1');
    $assignmentStmt->execute(['id' => $assignmentId]);
    $assignment = $assignmentStmt->fetch();

    if (!$assignment) {
        throw new RuntimeException('Data tugas tidak ditemukan.');
    }

    $submittedAt = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $deadline = new DateTimeImmutable((string) $assignment['deadline_at']);
    $onTime = $submittedAt <= $deadline->format('Y-m-d H:i:s') ? 1 : 0;

    $insertResult = $pdo->prepare(
        'INSERT INTO field_results (
            assignment_id,
            realisasi_amount,
            penagihan_berhasil,
            verval_complete,
            gps_valid,
            on_time,
            document_completeness,
            latitude,
            longitude,
            accuracy,
            captured_at,
            submitted_at,
            user_agent,
            ip_address
        ) VALUES (
            :assignment_id,
            :realisasi_amount,
            :penagihan_berhasil,
            :verval_complete,
            1,
            :on_time,
            :document_completeness,
            :latitude,
            :longitude,
            :accuracy,
            :captured_at,
            :submitted_at,
            :user_agent,
            :ip_address
        )
        ON DUPLICATE KEY UPDATE
            realisasi_amount = VALUES(realisasi_amount),
            penagihan_berhasil = VALUES(penagihan_berhasil),
            verval_complete = VALUES(verval_complete),
            gps_valid = VALUES(gps_valid),
            on_time = VALUES(on_time),
            document_completeness = VALUES(document_completeness),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            accuracy = VALUES(accuracy),
            captured_at = VALUES(captured_at),
            submitted_at = VALUES(submitted_at),
            user_agent = VALUES(user_agent),
            ip_address = VALUES(ip_address)'
    );

    $insertResult->execute([
        'assignment_id' => $assignmentId,
        'realisasi_amount' => $realisasi,
        'penagihan_berhasil' => $penagihan,
        'verval_complete' => $vervalComplete ? 1 : 0,
        'on_time' => $onTime,
        'document_completeness' => $doc,
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'accuracy' => is_numeric($accuracy) ? (float) $accuracy : null,
        'captured_at' => $submittedAt,
        'submitted_at' => $submittedAt,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255),
        'ip_address' => substr(clientIpAddress(), 0, 64),
    ]);

    $weightsStmt = $pdo->query('SELECT component_key, weight FROM kpi_weights');
    $weights = [];
    foreach ($weightsStmt->fetchAll() as $weight) {
        $weights[$weight['component_key']] = (float) $weight['weight'];
    }

    $components = calculateKpiComponents([
        'target_amount' => (float) $assignment['target_amount'],
        'realisasi_amount' => $realisasi,
        'penagihan_berhasil' => $penagihan,
        'verval_complete' => $vervalComplete,
        'gps_valid' => 1,
        'on_time' => $onTime,
        'document_completeness' => $doc,
    ]);

    $finalScore = weightedKpiScore($components, $weights);
    $category = kpiCategory($finalScore);

    $kpiStmt = $pdo->prepare(
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

    $kpiStmt->execute([
        'assignment_id' => $assignmentId,
        'target_achievement' => $components['target_achievement'],
        'collection_success' => $components['collection_success'],
        'verification_rate' => $components['verification_rate'],
        'gps_validity' => $components['gps_validity'],
        'timeliness' => $components['timeliness'],
        'document_completeness' => $components['document_completeness'],
        'final_score' => $finalScore,
        'category' => $category,
        'calculated_at' => $submittedAt,
    ]);

    $pdo->commit();

    header('Location: /public/input_hasil.php?message=' . urlencode('Hasil disimpan, GPS tervalidasi, KPI diperbarui.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirectError('Gagal menyimpan hasil: ' . $e->getMessage());
}
