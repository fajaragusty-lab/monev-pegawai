USE monev_pegawai;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE incentive_results;
TRUNCATE TABLE incentive_funds;
TRUNCATE TABLE kpi_scores;
TRUNCATE TABLE assignments;
TRUNCATE TABLE incentive_settings;
TRUNCATE TABLE kpi_weights;
TRUNCATE TABLE periods;
TRUNCATE TABLE job_types;
TRUNCATE TABLE taxpayers;
TRUNCATE TABLE employees;
TRUNCATE TABLE regions;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO regions (id, code, name, center_latitude, center_longitude, radius_meters) VALUES
(1, 'WIL-01', 'Wilayah Utara', -6.1753871, 106.8249641, 500),
(2, 'WIL-02', 'Wilayah Selatan', -6.2614920, 106.8105990, 600);

INSERT INTO employees (id, nip, name, username, password_hash, position, region_id, phone, role, status) VALUES
(1, 'ADM001', 'Admin Monitoring', 'admin', '$2y$10$I.9NRxcz7OAIYG3yhHmXK.Ibxi4YJRSp22e/nHOu/h7DGUjfxpPfC', 'Koordinator', 1, '081200000001', 'admin', 'active'),
(2, 'PTG001', 'Petugas Satu', 'petugas', '$2y$10$I.9NRxcz7OAIYG3yhHmXK.Ibxi4YJRSp22e/nHOu/h7DGUjfxpPfC', 'Petugas Lapangan', 1, '081200000002', 'petugas', 'active'),
(3, 'PTG002', 'Petugas Dua', 'petugas2', '$2y$10$I.9NRxcz7OAIYG3yhHmXK.Ibxi4YJRSp22e/nHOu/h7DGUjfxpPfC', 'Petugas Lapangan', 2, '081200000003', 'petugas', 'active');

INSERT INTO taxpayers (id, tax_number, name, region_id, address, latitude, longitude, status) VALUES
(1, 'NPWP-001', 'PT Maju Jaya', 1, 'Jl. Merdeka No. 10, Jakarta', -6.1753871, 106.8249641, 'active'),
(2, 'NPWP-002', 'CV Sinar Baru', 1, 'Jl. Kebon Sirih No. 8, Jakarta', -6.1830150, 106.8301330, 'active'),
(3, 'NPWP-003', 'Toko Mandiri', 2, 'Jl. RS Fatmawati No. 5, Jakarta Selatan', -6.2614920, 106.8105990, 'active');

INSERT INTO job_types (id, name, description, default_target, status) VALUES
(1, 'Penagihan Lapangan', 'Penagihan langsung ke lokasi wajib pajak.', 1, 'active'),
(2, 'Verifikasi Data', 'Pemeriksaan dan verval data wajib pajak.', 1, 'active'),
(3, 'Kunjungan Reminder', 'Kunjungan pengingat pembayaran.', 1, 'active');

INSERT INTO periods (id, code, name, start_date, end_date, status) VALUES
(1, '2026-W30', 'Periode Minggu 30 2026', '2026-07-20', '2026-07-26', 'closed'),
(2, '2026-W31', 'Periode Minggu 31 2026', '2026-07-27', '2026-08-02', 'open');

INSERT INTO kpi_weights (id, metric_key, metric_name, weight_percent) VALUES
(1, 'target', 'Target', 20),
(2, 'realisasi', 'Realisasi', 15),
(3, 'penagihan_berhasil', 'Penagihan Berhasil', 20),
(4, 'verval', 'Verval', 10),
(5, 'gps_valid', 'GPS Valid', 10),
(6, 'ketepatan_waktu', 'Ketepatan Waktu', 10),
(7, 'kelengkapan_dokumen', 'Kelengkapan Dokumen', 15);

INSERT INTO incentive_settings (id, name, description, minimum_kpi, bonus_multiplier, status) VALUES
(1, 'Setting Default', 'Pegawai dengan KPI minimal 60 memperoleh proporsi insentif penuh.', 60, 1, 'active');

INSERT INTO assignments (id, period_id, employee_id, taxpayer_id, job_type_id, assigned_by, title, target_value, deadline_date, priority, notes, status, accepted_at, submitted_at, verified_at, verified_by, verification_notes, gps_latitude, gps_longitude, distance_meters, gps_valid, location_photo_path, evidence_file_path, result_notes, actual_value, amount_collected, document_complete, created_at, updated_at) VALUES
(1, 1, 2, 1, 1, 1, 'Tagih tunggakan PT Maju Jaya', 1, '2026-07-24', 'high', 'Prioritas penagihan mingguan.', 'approved', '2026-07-21 08:00:00', '2026-07-22 10:00:00', '2026-07-22 15:00:00', 1, 'Lengkap dan valid.', -6.1754000, 106.8249000, 7.75, 1, 'public/uploads/field-photos/sample-photo-1.png', 'public/uploads/evidence/sample-evidence-1.pdf', 'Wajib pajak hadir dan menyetujui pelunasan.', 1, 5000000, 1, '2026-07-20 09:00:00', '2026-07-22 15:00:00'),
(2, 1, 3, 3, 2, 1, 'Verval data Toko Mandiri', 1, '2026-07-25', 'medium', 'Periksa data alamat dan status tunggakan.', 'submitted', '2026-07-21 09:00:00', '2026-07-24 13:00:00', NULL, NULL, NULL, -6.2615000, 106.8106200, 3.15, 1, 'public/uploads/field-photos/sample-photo-2.png', 'public/uploads/evidence/sample-evidence-2.pdf', 'Dokumen sudah diunggah, menunggu review admin.', 1, 0, 1, '2026-07-20 09:30:00', '2026-07-24 13:00:00'),
(3, 2, 2, 2, 3, 1, 'Reminder pembayaran CV Sinar Baru', 1, '2026-08-01', 'medium', 'Lakukan reminder dan catat respon wajib pajak.', 'assigned', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, 0, 0, '2026-07-29 09:30:00', '2026-07-29 09:30:00');

INSERT INTO kpi_scores (period_id, employee_id, target_count, realization_count, collection_success_count, verification_count, gps_valid_count, on_time_count, document_complete_count, target_ratio, realization_ratio, collection_success_ratio, verification_ratio, gps_valid_ratio, on_time_ratio, document_complete_ratio, score_total, calculated_at) VALUES
(1, 2, 1, 1, 1, 1, 1, 1, 1, 100, 100, 100, 100, 100, 100, 100, 100, '2026-07-25 18:00:00'),
(1, 3, 1, 1, 0, 0, 1, 1, 1, 100, 100, 0, 0, 100, 100, 100, 55, '2026-07-25 18:00:00');

INSERT INTO incentive_funds (id, period_id, weekly_fund, monthly_fund, created_at, updated_at) VALUES
(1, 1, 3000000, 5000000, '2026-07-25 18:10:00', '2026-07-25 18:10:00');

INSERT INTO incentive_results (period_id, employee_id, kpi_score, proportion, weekly_amount, monthly_amount, total_amount, calculated_at) VALUES
(1, 2, 100, 0.645161, 1935483.00, 3225806.00, 5161289.00, '2026-07-25 18:15:00'),
(1, 3, 55, 0.354839, 1064517.00, 1774194.00, 2838711.00, '2026-07-25 18:15:00');
