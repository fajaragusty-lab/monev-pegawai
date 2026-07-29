USE monev_pegawai;

INSERT INTO users (id, name, role) VALUES
    (1, 'Admin Monitoring', 'admin'),
    (2, 'Rina Petugas', 'petugas'),
    (3, 'Budi Petugas', 'petugas')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    role = VALUES(role);

INSERT INTO taxpayers (id, name, address) VALUES
    (1, 'PT Sumber Makmur', 'Jl. Mawar No. 10'),
    (2, 'CV Cakrawala Niaga', 'Jl. Merdeka No. 21'),
    (3, 'UD Sejahtera Abadi', 'Jl. Melati No. 3')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    address = VALUES(address);

INSERT INTO assignments (id, petugas_id, taxpayer_id, target_amount, deadline_at) VALUES
    (1, 2, 1, 15000000, DATE_ADD(NOW(), INTERVAL 3 DAY)),
    (2, 2, 2, 12000000, DATE_ADD(NOW(), INTERVAL 1 DAY)),
    (3, 3, 3, 18000000, DATE_ADD(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE
    petugas_id = VALUES(petugas_id),
    taxpayer_id = VALUES(taxpayer_id),
    target_amount = VALUES(target_amount),
    deadline_at = VALUES(deadline_at);

INSERT INTO kpi_weights (component_key, weight) VALUES
    ('target_achievement', 0.25),
    ('collection_success', 0.25),
    ('verification_rate', 0.15),
    ('gps_validity', 0.10),
    ('timeliness', 0.10),
    ('document_completeness', 0.15)
ON DUPLICATE KEY UPDATE
    weight = VALUES(weight);

INSERT INTO field_results (
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
) VALUES
    (1, 14500000, 14100000, 1, 1, 1, 95, -6.2088000, 106.8456000, 8.4, NOW(), NOW(), 'SeedData-Agent', '127.0.0.1'),
    (2, 10000000, 9000000, 1, 1, 0, 88, -6.9147440, 107.6098100, 12.0, NOW(), NOW(), 'SeedData-Agent', '127.0.0.1'),
    (3, 18200000, 17600000, 1, 1, 1, 92, -7.2574720, 112.7520900, 6.3, NOW(), NOW(), 'SeedData-Agent', '127.0.0.1')
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
    ip_address = VALUES(ip_address);

INSERT INTO kpi_results (
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
)
SELECT
    a.id,
    LEAST((fr.realisasi_amount / NULLIF(a.target_amount, 0)) * 100, 100) AS target_achievement,
    LEAST((fr.penagihan_berhasil / NULLIF(fr.realisasi_amount, 0)) * 100, 100) AS collection_success,
    IF(fr.verval_complete = 1, 100, 0) AS verification_rate,
    IF(fr.gps_valid = 1, 100, 0) AS gps_validity,
    IF(fr.on_time = 1, 100, 0) AS timeliness,
    fr.document_completeness,
    ROUND(
        (
            LEAST((fr.realisasi_amount / NULLIF(a.target_amount, 0)) * 100, 100) * 0.25 +
            LEAST((fr.penagihan_berhasil / NULLIF(fr.realisasi_amount, 0)) * 100, 100) * 0.25 +
            IF(fr.verval_complete = 1, 100, 0) * 0.15 +
            IF(fr.gps_valid = 1, 100, 0) * 0.10 +
            IF(fr.on_time = 1, 100, 0) * 0.10 +
            fr.document_completeness * 0.15
        ) / 1.0,
        2
    ) AS final_score,
    CASE
        WHEN ROUND(
            (
                LEAST((fr.realisasi_amount / NULLIF(a.target_amount, 0)) * 100, 100) * 0.25 +
                LEAST((fr.penagihan_berhasil / NULLIF(fr.realisasi_amount, 0)) * 100, 100) * 0.25 +
                IF(fr.verval_complete = 1, 100, 0) * 0.15 +
                IF(fr.gps_valid = 1, 100, 0) * 0.10 +
                IF(fr.on_time = 1, 100, 0) * 0.10 +
                fr.document_completeness * 0.15
            ) / 1.0,
            2
        ) >= 85 THEN 'A (Sangat Baik)'
        WHEN ROUND(
            (
                LEAST((fr.realisasi_amount / NULLIF(a.target_amount, 0)) * 100, 100) * 0.25 +
                LEAST((fr.penagihan_berhasil / NULLIF(fr.realisasi_amount, 0)) * 100, 100) * 0.25 +
                IF(fr.verval_complete = 1, 100, 0) * 0.15 +
                IF(fr.gps_valid = 1, 100, 0) * 0.10 +
                IF(fr.on_time = 1, 100, 0) * 0.10 +
                fr.document_completeness * 0.15
            ) / 1.0,
            2
        ) >= 70 THEN 'B (Baik)'
        WHEN ROUND(
            (
                LEAST((fr.realisasi_amount / NULLIF(a.target_amount, 0)) * 100, 100) * 0.25 +
                LEAST((fr.penagihan_berhasil / NULLIF(fr.realisasi_amount, 0)) * 100, 100) * 0.25 +
                IF(fr.verval_complete = 1, 100, 0) * 0.15 +
                IF(fr.gps_valid = 1, 100, 0) * 0.10 +
                IF(fr.on_time = 1, 100, 0) * 0.10 +
                fr.document_completeness * 0.15
            ) / 1.0,
            2
        ) >= 55 THEN 'C (Cukup)'
        ELSE 'D (Perlu Pembinaan)'
    END AS category,
    NOW()
FROM assignments a
INNER JOIN field_results fr ON fr.assignment_id = a.id
ON DUPLICATE KEY UPDATE
    target_achievement = VALUES(target_achievement),
    collection_success = VALUES(collection_success),
    verification_rate = VALUES(verification_rate),
    gps_validity = VALUES(gps_validity),
    timeliness = VALUES(timeliness),
    document_completeness = VALUES(document_completeness),
    final_score = VALUES(final_score),
    category = VALUES(category),
    calculated_at = VALUES(calculated_at);
