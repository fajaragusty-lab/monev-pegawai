<?php

declare(strict_types=1);

/**
 * KPI formula (0-100):
 * final_score = sum(component_score * weight) / sum(weight)
 *
 * Components:
 * - target_achievement: min(realisasi / target * 100, 100)
 * - collection_success: min(penagihan_berhasil / realisasi * 100, 100)
 * - verification_rate: verval_complete ? 100 : 0
 * - gps_validity: gps_valid ? 100 : 0
 * - timeliness: on_time ? 100 : 0
 * - document_completeness: min(document_completeness, 100)
 */
function calculateKpiComponents(array $row): array
{
    $target = max((float) ($row['target_amount'] ?? 0), 0.0);
    $realisasi = max((float) ($row['realisasi_amount'] ?? 0), 0.0);
    $penagihan = max((float) ($row['penagihan_berhasil'] ?? 0), 0.0);
    $doc = max(min((float) ($row['document_completeness'] ?? 0), 100.0), 0.0);

    $targetAchievement = $target > 0 ? min(($realisasi / $target) * 100.0, 100.0) : 0.0;
    $collectionSuccess = $realisasi > 0 ? min(($penagihan / $realisasi) * 100.0, 100.0) : 0.0;

    $verificationRate = !empty($row['verval_complete']) ? 100.0 : 0.0;
    $gpsValidity = !empty($row['gps_valid']) ? 100.0 : 0.0;
    $timeliness = !empty($row['on_time']) ? 100.0 : 0.0;

    return [
        'target_achievement' => round($targetAchievement, 2),
        'collection_success' => round($collectionSuccess, 2),
        'verification_rate' => $verificationRate,
        'gps_validity' => $gpsValidity,
        'timeliness' => $timeliness,
        'document_completeness' => round($doc, 2),
    ];
}

function weightedKpiScore(array $components, array $weights): float
{
    $weighted = 0.0;
    $weightTotal = 0.0;

    foreach ($components as $component => $value) {
        $weight = isset($weights[$component]) ? (float) $weights[$component] : 0.0;
        if ($weight <= 0) {
            continue;
        }

        $weighted += ((float) $value) * $weight;
        $weightTotal += $weight;
    }

    if ($weightTotal <= 0) {
        return 0.0;
    }

    return round($weighted / $weightTotal, 2);
}

function kpiCategory(float $score): string
{
    if ($score >= 85) {
        return 'A (Sangat Baik)';
    }

    if ($score >= 70) {
        return 'B (Baik)';
    }

    if ($score >= 55) {
        return 'C (Cukup)';
    }

    return 'D (Perlu Pembinaan)';
}
