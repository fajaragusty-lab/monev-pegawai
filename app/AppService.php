<?php
class AppService
{
    public function __construct(private Database $db, private array $config)
    {
    }

    public function modules(): array
    {
        return $this->config['modules'];
    }

    public function module(string $slug): array
    {
        $module = $this->config['modules'][$slug] ?? null;
        if (!$module) {
            throw new InvalidArgumentException('Modul master data tidak ditemukan.');
        }
        return $module;
    }

    public function moduleRecords(string $slug): array
    {
        $module = $this->module($slug);
        return $this->db->all('SELECT * FROM ' . $module['table'] . ' ORDER BY id DESC');
    }

    public function moduleRecord(string $slug, int $id): ?array
    {
        $module = $this->module($slug);
        return $this->db->one('SELECT * FROM ' . $module['table'] . ' WHERE id = :id', ['id' => $id]);
    }

    public function saveModuleRecord(string $slug, array $input, ?int $id = null): void
    {
        $module = $this->module($slug);
        $data = [];

        foreach ($module['fields'] as $field => $meta) {
            $value = trim((string) ($input[$field] ?? ''));
            $isRequired = (bool) ($meta['required'] ?? false);
            if ($id === null && ($meta['required_on_create'] ?? false)) {
                $isRequired = true;
            }

            if ($isRequired && $value === '') {
                throw new RuntimeException($meta['label'] . ' wajib diisi.');
            }

            if ($field === 'password_hash') {
                if ($value !== '') {
                    $data[$field] = password_hash($value, PASSWORD_BCRYPT);
                }
                continue;
            }

            if ($value === '') {
                $data[$field] = in_array($meta['type'], ['number', 'select'], true) && !isset($meta['default']) ? null : ($meta['default'] ?? null);
                continue;
            }

            $data[$field] = match ($meta['type']) {
                'number' => is_numeric($value) ? $value : null,
                default => $value,
            };
        }

        if ($slug === 'employees') {
            $params = ['username' => $data['username'] ?? $input['username'], 'nip' => $data['nip'] ?? $input['nip']];
            $usernameSql = 'SELECT id FROM employees WHERE username = :username';
            $nipSql = 'SELECT id FROM employees WHERE nip = :nip';
            if ($id !== null) {
                $params['id'] = $id;
                $usernameSql .= ' AND id <> :id';
                $nipSql .= ' AND id <> :id';
            }
            if ($this->db->one($usernameSql . ' LIMIT 1', $params)) {
                throw new RuntimeException('Username sudah digunakan.');
            }
            if ($this->db->one($nipSql . ' LIMIT 1', $params)) {
                throw new RuntimeException('NIP sudah digunakan.');
            }
        }

        if ($slug === 'taxpayers') {
            $params = ['tax_number' => $data['tax_number'] ?? $input['tax_number']];
            $sql = 'SELECT id FROM taxpayers WHERE tax_number = :tax_number';
            if ($id !== null) {
                $params['id'] = $id;
                $sql .= ' AND id <> :id';
            }
            if ($this->db->one($sql . ' LIMIT 1', $params)) {
                throw new RuntimeException('ID pajak / NPWP sudah digunakan.');
            }
        }

        if ($id === null) {
            if ($slug === 'employees' && empty($data['password_hash'])) {
                throw new RuntimeException('Password wajib diisi untuk pegawai baru.');
            }
            $this->db->insert($module['table'], $data);
            return;
        }

        if (empty($data)) {
            throw new RuntimeException('Tidak ada data yang diubah.');
        }
        $this->db->update($module['table'], $data, $id);
    }

    public function deleteModuleRecord(string $slug, int $id): void
    {
        $module = $this->module($slug);
        $this->db->delete($module['table'], $id);
    }

    public function selectOptions(string $table, string $label = 'name'): array
    {
        $rows = $this->db->all('SELECT id, ' . $label . ' AS label FROM ' . $table . ' ORDER BY ' . $label . ' ASC');
        $options = [];
        foreach ($rows as $row) {
            $options[(string) $row['id']] = $row['label'];
        }
        return $options;
    }

    public function dashboardSummary(array $user): array
    {
        $scope = $user['role'] === 'admin' ? '' : ' WHERE a.employee_id = :employee_id ';
        $params = $user['role'] === 'admin' ? [] : ['employee_id' => $user['id']];

        return [
            'pegawai' => (int) ($this->db->one('SELECT COUNT(*) AS total FROM employees') ['total'] ?? 0),
            'wajib_pajak' => (int) ($this->db->one('SELECT COUNT(*) AS total FROM taxpayers') ['total'] ?? 0),
            'penugasan' => (int) (($this->db->one('SELECT COUNT(*) AS total FROM assignments a' . $scope, $params))['total'] ?? 0),
            'menunggu_verifikasi' => (int) (($this->db->one('SELECT COUNT(*) AS total FROM assignments a ' . ($scope ? 'WHERE a.employee_id = :employee_id AND ' : 'WHERE ') . 'a.status = :status', $params + ['status' => 'submitted']))['total'] ?? 0),
            'disetujui' => (int) (($this->db->one('SELECT COUNT(*) AS total FROM assignments a ' . ($scope ? 'WHERE a.employee_id = :employee_id AND ' : 'WHERE ') . 'a.status = :status', $params + ['status' => 'approved']))['total'] ?? 0),
            'insentif_total' => (float) (($this->db->one(
                'SELECT COALESCE(SUM(ir.total_amount), 0) AS total FROM incentive_results ir' .
                ($user['role'] === 'admin' ? '' : ' WHERE ir.employee_id = :employee_id'),
                $params
            ))['total'] ?? 0),
        ];
    }

    public function listAssignments(array $user, ?int $periodId = null): array
    {
        $conditions = [];
        $params = [];
        if ($user['role'] !== 'admin') {
            $conditions[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $user['id'];
        }
        if ($periodId) {
            $conditions[] = 'a.period_id = :period_id';
            $params['period_id'] = $periodId;
        }
        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->all(
            'SELECT a.*, p.name AS period_name, e.name AS employee_name, t.name AS taxpayer_name, jt.name AS job_type_name
             FROM assignments a
             INNER JOIN periods p ON p.id = a.period_id
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN taxpayers t ON t.id = a.taxpayer_id
             INNER JOIN job_types jt ON jt.id = a.job_type_id' .
             $where .
             ' ORDER BY a.id DESC',
            $params
        );
    }

    public function getAssignment(int $id, ?array $user = null): ?array
    {
        $assignment = $this->db->one(
            'SELECT a.*, p.name AS period_name, p.start_date, p.end_date, e.name AS employee_name, e.username AS employee_username,
                    t.name AS taxpayer_name, t.address AS taxpayer_address, t.latitude AS taxpayer_latitude, t.longitude AS taxpayer_longitude,
                    r.name AS region_name, r.radius_meters, r.center_latitude, r.center_longitude,
                    jt.name AS job_type_name, admin.name AS assigned_by_name, verifier.name AS verifier_name
             FROM assignments a
             INNER JOIN periods p ON p.id = a.period_id
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN taxpayers t ON t.id = a.taxpayer_id
             LEFT JOIN regions r ON r.id = t.region_id
             INNER JOIN job_types jt ON jt.id = a.job_type_id
             INNER JOIN employees admin ON admin.id = a.assigned_by
             LEFT JOIN employees verifier ON verifier.id = a.verified_by
             WHERE a.id = :id',
            ['id' => $id]
        );

        if (!$assignment) {
            return null;
        }

        if ($user && $user['role'] !== 'admin' && (int) $assignment['employee_id'] !== (int) $user['id']) {
            return null;
        }

        return $assignment;
    }

    public function createAssignment(array $input, int $adminId): void
    {
        foreach (['period_id', 'employee_id', 'taxpayer_id', 'job_type_id', 'title', 'deadline_date'] as $required) {
            if (trim((string) ($input[$required] ?? '')) === '') {
                throw new RuntimeException('Form penugasan belum lengkap.');
            }
        }

        $jobType = $this->db->one('SELECT default_target FROM job_types WHERE id = :id', ['id' => (int) $input['job_type_id']]);
        $this->db->insert('assignments', [
            'period_id' => (int) $input['period_id'],
            'employee_id' => (int) $input['employee_id'],
            'taxpayer_id' => (int) $input['taxpayer_id'],
            'job_type_id' => (int) $input['job_type_id'],
            'assigned_by' => $adminId,
            'title' => trim((string) $input['title']),
            'target_value' => $input['target_value'] !== '' ? (float) $input['target_value'] : (float) ($jobType['default_target'] ?? 1),
            'deadline_date' => $input['deadline_date'],
            'priority' => $input['priority'] ?: 'medium',
            'notes' => trim((string) ($input['notes'] ?? '')),
            'status' => 'assigned',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function acceptAssignment(int $id, array $user): void
    {
        $assignment = $this->getAssignment($id, $user);
        if (!$assignment) {
            throw new RuntimeException('Tugas tidak ditemukan.');
        }
        if (!in_array($assignment['status'], ['assigned', 'rejected'], true)) {
            throw new RuntimeException('Status tugas tidak bisa diterima.');
        }
        $this->db->update('assignments', [
            'status' => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $id);
    }

    public function submitAssignment(int $id, array $user, array $input, array $files): void
    {
        $assignment = $this->getAssignment($id, $user);
        if (!$assignment) {
            throw new RuntimeException('Tugas tidak ditemukan.');
        }
        if (!in_array($assignment['status'], ['accepted', 'rejected'], true)) {
            throw new RuntimeException('Tugas belum siap disubmit.');
        }

        foreach (['gps_latitude', 'gps_longitude', 'result_notes', 'actual_value'] as $required) {
            if (trim((string) ($input[$required] ?? '')) === '') {
                throw new RuntimeException('Data pelaksanaan belum lengkap.');
            }
        }

        $targetLat = $assignment['taxpayer_latitude'] !== null ? (float) $assignment['taxpayer_latitude'] : (float) ($assignment['center_latitude'] ?? 0);
        $targetLng = $assignment['taxpayer_longitude'] !== null ? (float) $assignment['taxpayer_longitude'] : (float) ($assignment['center_longitude'] ?? 0);
        if ($targetLat === 0.0 && $targetLng === 0.0) {
            throw new RuntimeException('Lokasi referensi wajib pajak / wilayah belum diatur.');
        }

        $gpsLat = (float) $input['gps_latitude'];
        $gpsLng = (float) $input['gps_longitude'];
        $distance = calculate_distance_meters($targetLat, $targetLng, $gpsLat, $gpsLng);
        $radius = (float) ($assignment['radius_meters'] ?: ($this->config['app']['default_radius_meters'] ?? 500));
        $gpsValid = $distance <= $radius;
        if (!$gpsValid) {
            throw new RuntimeException('GPS tidak valid. Jarak petugas ' . number_format($distance, 2, ',', '.') . ' meter dari titik referensi, melebihi radius ' . $radius . ' meter.');
        }

        $photoPath = upload_file($files['location_photo'] ?? [], 'field-photos', ['jpg', 'jpeg', 'png']);
        $evidencePath = upload_file($files['evidence_file'] ?? [], 'evidence', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

        if (!$photoPath || !$evidencePath) {
            throw new RuntimeException('Foto lokasi dan file bukti wajib diupload.');
        }

        $documentComplete = !empty($input['document_complete']) ? 1 : 0;
        $this->db->update('assignments', [
            'status' => 'submitted',
            'gps_latitude' => $gpsLat,
            'gps_longitude' => $gpsLng,
            'distance_meters' => $distance,
            'gps_valid' => 1,
            'location_photo_path' => $photoPath,
            'evidence_file_path' => $evidencePath,
            'result_notes' => trim((string) $input['result_notes']),
            'actual_value' => (float) $input['actual_value'],
            'amount_collected' => $input['amount_collected'] !== '' ? (float) $input['amount_collected'] : 0,
            'document_complete' => $documentComplete,
            'submitted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $id);
    }

    public function verificationList(?int $periodId = null): array
    {
        $conditions = ['a.status = :status'];
        $params = ['status' => 'submitted'];
        if ($periodId) {
            $conditions[] = 'a.period_id = :period_id';
            $params['period_id'] = $periodId;
        }

        return $this->db->all(
            'SELECT a.*, p.name AS period_name, e.name AS employee_name, t.name AS taxpayer_name, jt.name AS job_type_name
             FROM assignments a
             INNER JOIN periods p ON p.id = a.period_id
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN taxpayers t ON t.id = a.taxpayer_id
             INNER JOIN job_types jt ON jt.id = a.job_type_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.submitted_at ASC',
            $params
        );
    }

    public function verifyAssignment(int $id, string $action, string $notes, int $verifierId): void
    {
        $assignment = $this->db->one('SELECT * FROM assignments WHERE id = :id', ['id' => $id]);
        if (!$assignment || $assignment['status'] !== 'submitted') {
            throw new RuntimeException('Data verifikasi tidak ditemukan.');
        }

        $status = $action === 'approve' ? 'approved' : 'rejected';
        $this->db->update('assignments', [
            'status' => $status,
            'verification_notes' => trim($notes),
            'verified_by' => $verifierId,
            'verified_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $id);
    }

    public function periods(): array
    {
        return $this->db->all('SELECT * FROM periods ORDER BY start_date DESC, id DESC');
    }

    public function employeesByRole(string $role = 'petugas'): array
    {
        return $this->db->all('SELECT * FROM employees WHERE role = :role AND status = :status ORDER BY name ASC', [
            'role' => $role,
            'status' => 'active',
        ]);
    }

    public function activeTaxpayers(): array
    {
        return $this->db->all('SELECT * FROM taxpayers WHERE status = :status ORDER BY name ASC', ['status' => 'active']);
    }

    public function activeJobTypes(): array
    {
        return $this->db->all('SELECT * FROM job_types WHERE status = :status ORDER BY name ASC', ['status' => 'active']);
    }

    public function recalculateKpi(int $periodId): void
    {
        $weights = $this->db->all('SELECT metric_key, weight_percent FROM kpi_weights ORDER BY id ASC');
        if (!$weights) {
            throw new RuntimeException('Bobot KPI belum tersedia.');
        }

        $employees = $this->db->all(
            'SELECT DISTINCT e.id, e.name
             FROM employees e
             INNER JOIN assignments a ON a.employee_id = e.id
             WHERE a.period_id = :period_id',
            ['period_id' => $periodId]
        );

        $this->db->execute('DELETE FROM kpi_scores WHERE period_id = :period_id', ['period_id' => $periodId]);

        foreach ($employees as $employee) {
            $metrics = $this->db->one(
                'SELECT
                    COUNT(*) AS target_count,
                    SUM(CASE WHEN status IN ("submitted", "approved") THEN 1 ELSE 0 END) AS realization_count,
                    SUM(CASE WHEN amount_collected > 0 THEN 1 ELSE 0 END) AS collection_success_count,
                    SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) AS verification_count,
                    SUM(CASE WHEN gps_valid = 1 THEN 1 ELSE 0 END) AS gps_valid_count,
                    SUM(CASE WHEN submitted_at IS NOT NULL AND DATE(submitted_at) <= deadline_date THEN 1 ELSE 0 END) AS on_time_count,
                    SUM(CASE WHEN document_complete = 1 THEN 1 ELSE 0 END) AS document_complete_count,
                    COALESCE(SUM(target_value), 0) AS target_value_total,
                    COALESCE(SUM(actual_value), 0) AS actual_value_total,
                    COALESCE(SUM(amount_collected), 0) AS amount_collected_total
                 FROM assignments
                 WHERE period_id = :period_id AND employee_id = :employee_id',
                ['period_id' => $periodId, 'employee_id' => $employee['id']]
            );

            $targetCount = max((int) ($metrics['target_count'] ?? 0), 1);
            $realizationCount = max((int) ($metrics['realization_count'] ?? 0), 1);

            $ratios = [
                'target' => min(100, (($metrics['actual_value_total'] ?? 0) / max((float) ($metrics['target_value_total'] ?: 1), 1)) * 100),
                'realisasi' => min(100, ((int) ($metrics['realization_count'] ?? 0) / $targetCount) * 100),
                'penagihan_berhasil' => min(100, ((int) ($metrics['collection_success_count'] ?? 0) / $targetCount) * 100),
                'verval' => min(100, ((int) ($metrics['verification_count'] ?? 0) / $realizationCount) * 100),
                'gps_valid' => min(100, ((int) ($metrics['gps_valid_count'] ?? 0) / $realizationCount) * 100),
                'ketepatan_waktu' => min(100, ((int) ($metrics['on_time_count'] ?? 0) / $realizationCount) * 100),
                'kelengkapan_dokumen' => min(100, ((int) ($metrics['document_complete_count'] ?? 0) / $realizationCount) * 100),
            ];

            $score = 0;
            foreach ($weights as $weight) {
                $metricKey = $weight['metric_key'];
                $score += ($ratios[$metricKey] ?? 0) * ((float) $weight['weight_percent'] / 100);
            }

            $this->db->insert('kpi_scores', [
                'period_id' => $periodId,
                'employee_id' => $employee['id'],
                'target_count' => (int) ($metrics['target_count'] ?? 0),
                'realization_count' => (int) ($metrics['realization_count'] ?? 0),
                'collection_success_count' => (int) ($metrics['collection_success_count'] ?? 0),
                'verification_count' => (int) ($metrics['verification_count'] ?? 0),
                'gps_valid_count' => (int) ($metrics['gps_valid_count'] ?? 0),
                'on_time_count' => (int) ($metrics['on_time_count'] ?? 0),
                'document_complete_count' => (int) ($metrics['document_complete_count'] ?? 0),
                'target_ratio' => round($ratios['target'], 2),
                'realization_ratio' => round($ratios['realisasi'], 2),
                'collection_success_ratio' => round($ratios['penagihan_berhasil'], 2),
                'verification_ratio' => round($ratios['verval'], 2),
                'gps_valid_ratio' => round($ratios['gps_valid'], 2),
                'on_time_ratio' => round($ratios['ketepatan_waktu'], 2),
                'document_complete_ratio' => round($ratios['kelengkapan_dokumen'], 2),
                'score_total' => round($score, 2),
                'calculated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function kpiScores(int $periodId): array
    {
        return $this->db->all(
            'SELECT ks.*, e.name AS employee_name
             FROM kpi_scores ks
             INNER JOIN employees e ON e.id = ks.employee_id
             WHERE ks.period_id = :period_id
             ORDER BY ks.score_total DESC, e.name ASC',
            ['period_id' => $periodId]
        );
    }

    public function rankings(int $periodId): array
    {
        $scores = $this->kpiScores($periodId);
        $rank = 1;
        foreach ($scores as &$score) {
            $score['ranking'] = $rank++;
        }
        return $scores;
    }

    public function incentiveSetting(): ?array
    {
        return $this->db->one('SELECT * FROM incentive_settings WHERE status = :status ORDER BY id DESC LIMIT 1', ['status' => 'active']);
    }

    public function incentiveFund(int $periodId): ?array
    {
        return $this->db->one('SELECT * FROM incentive_funds WHERE period_id = :period_id', ['period_id' => $periodId]);
    }

    public function saveIncentiveFund(int $periodId, float $weeklyFund, float $monthlyFund): void
    {
        $existing = $this->incentiveFund($periodId);
        $payload = [
            'period_id' => $periodId,
            'weekly_fund' => $weeklyFund,
            'monthly_fund' => $monthlyFund,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db->update('incentive_funds', $payload, (int) $existing['id']);
            return;
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('incentive_funds', $payload);
    }

    public function recalculateIncentives(int $periodId): void
    {
        $fund = $this->incentiveFund($periodId);
        if (!$fund) {
            throw new RuntimeException('Dana insentif mingguan/bulanan belum diinput.');
        }
        $scores = $this->kpiScores($periodId);
        if (!$scores) {
            throw new RuntimeException('Hitung KPI terlebih dahulu sebelum insentif.');
        }
        $setting = $this->incentiveSetting() ?? ['minimum_kpi' => 0, 'bonus_multiplier' => 1];

        $eligibleScores = [];
        $totalEligible = 0;
        foreach ($scores as $score) {
            $eligible = (float) $score['score_total'] >= (float) $setting['minimum_kpi']
                ? (float) $score['score_total'] * (float) $setting['bonus_multiplier']
                : 0;
            $eligibleScores[$score['employee_id']] = $eligible;
            $totalEligible += $eligible;
        }

        $this->db->execute('DELETE FROM incentive_results WHERE period_id = :period_id', ['period_id' => $periodId]);
        foreach ($scores as $score) {
            $eligible = $eligibleScores[$score['employee_id']];
            $proportion = $totalEligible > 0 ? $eligible / $totalEligible : 0;
            $weekly = $proportion * (float) $fund['weekly_fund'];
            $monthly = $proportion * (float) $fund['monthly_fund'];
            $this->db->insert('incentive_results', [
                'period_id' => $periodId,
                'employee_id' => $score['employee_id'],
                'kpi_score' => $score['score_total'],
                'proportion' => round($proportion, 6),
                'weekly_amount' => round($weekly, 2),
                'monthly_amount' => round($monthly, 2),
                'total_amount' => round($weekly + $monthly, 2),
                'calculated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function incentiveResults(int $periodId): array
    {
        return $this->db->all(
            'SELECT ir.*, e.name AS employee_name
             FROM incentive_results ir
             INNER JOIN employees e ON e.id = ir.employee_id
             WHERE ir.period_id = :period_id
             ORDER BY ir.total_amount DESC, e.name ASC',
            ['period_id' => $periodId]
        );
    }

    public function filterIncentiveResultsForUser(array $rows, array $user): array
    {
        if (($user['role'] ?? 'petugas') === 'admin') {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row) => (int) $row['employee_id'] === (int) $user['id']));
    }

    public function reportData(string $report, int $periodId, array $user): array
    {
        return match ($report) {
            'kpi' => $this->kpiScores($periodId),
            'collections' => $this->collectionReport($periodId, $user),
            'gps' => $this->gpsReport($periodId, $user),
            'incentives' => $this->filterIncentiveResultsForUser($this->incentiveResults($periodId), $user),
            'weekly' => $this->weeklyChart($periodId, $user),
            'monthly' => $this->monthlyChart($periodId, $user),
            default => [],
        };
    }

    public function collectionReport(int $periodId, array $user): array
    {
        $conditions = ['a.period_id = :period_id'];
        $params = ['period_id' => $periodId];
        if ($user['role'] !== 'admin') {
            $conditions[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $user['id'];
        }
        return $this->db->all(
            'SELECT a.title, e.name AS employee_name, t.name AS taxpayer_name, jt.name AS job_type_name,
                    a.target_value, a.actual_value, a.amount_collected, a.status, a.submitted_at
             FROM assignments a
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN taxpayers t ON t.id = a.taxpayer_id
             INNER JOIN job_types jt ON jt.id = a.job_type_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.submitted_at DESC, a.id DESC',
            $params
        );
    }

    public function gpsReport(int $periodId, array $user): array
    {
        $conditions = ['a.period_id = :period_id'];
        $params = ['period_id' => $periodId];
        if ($user['role'] !== 'admin') {
            $conditions[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $user['id'];
        }
        return $this->db->all(
            'SELECT a.title, e.name AS employee_name, t.name AS taxpayer_name, a.gps_latitude, a.gps_longitude,
                    a.distance_meters, a.gps_valid, a.submitted_at
             FROM assignments a
             INNER JOIN employees e ON e.id = a.employee_id
             INNER JOIN taxpayers t ON t.id = a.taxpayer_id
             WHERE ' . implode(' AND ', $conditions) . '
             ORDER BY a.submitted_at DESC, a.id DESC',
            $params
        );
    }

    public function weeklyChart(int $periodId, array $user): array
    {
        $conditions = ['period_id = :period_id', 'submitted_at IS NOT NULL'];
        $params = ['period_id' => $periodId];
        if ($user['role'] !== 'admin') {
            $conditions[] = 'employee_id = :employee_id';
            $params['employee_id'] = $user['id'];
        }
        return $this->db->all(
            'SELECT YEARWEEK(submitted_at, 1) AS week_code, COUNT(*) AS total_tasks, COALESCE(SUM(amount_collected), 0) AS total_collected
             FROM assignments
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY YEARWEEK(submitted_at, 1)
             ORDER BY week_code ASC',
            $params
        );
    }

    public function monthlyChart(int $periodId, array $user): array
    {
        $conditions = ['period_id = :period_id', 'submitted_at IS NOT NULL'];
        $params = ['period_id' => $periodId];
        if ($user['role'] !== 'admin') {
            $conditions[] = 'employee_id = :employee_id';
            $params['employee_id'] = $user['id'];
        }
        return $this->db->all(
            'SELECT DATE_FORMAT(submitted_at, "%Y-%m") AS month_code, COUNT(*) AS total_tasks, COALESCE(SUM(amount_collected), 0) AS total_collected
             FROM assignments
             WHERE ' . implode(' AND ', $conditions) . '
             GROUP BY DATE_FORMAT(submitted_at, "%Y-%m")
             ORDER BY month_code ASC',
            $params
        );
    }

    public function period(int $periodId): ?array
    {
        return $this->db->one('SELECT * FROM periods WHERE id = :id', ['id' => $periodId]);
    }

    public function exportRows(string $report, int $periodId, array $user): array
    {
        $period = $this->period($periodId);
        $title = 'Laporan ' . strtoupper($report) . ' - ' . ($period['name'] ?? 'Periode');
        $data = $this->reportData($report, $periodId, $user);

        $headers = [];
        $rows = [];
        if ($report === 'kpi') {
            $headers = ['Pegawai', 'Target', 'Realisasi', 'Berhasil', 'GPS Valid', 'Tepat Waktu', 'Kelengkapan', 'Skor'];
            foreach ($data as $row) {
                $rows[] = [
                    $row['employee_name'],
                    $row['target_count'],
                    $row['realization_count'],
                    $row['collection_success_count'],
                    $row['gps_valid_count'],
                    $row['on_time_count'],
                    $row['document_complete_count'],
                    $row['score_total'],
                ];
            }
        } elseif ($report === 'collections') {
            $headers = ['Judul', 'Pegawai', 'Wajib Pajak', 'Jenis Pekerjaan', 'Target', 'Realisasi', 'Nominal Tagihan', 'Status'];
            foreach ($data as $row) {
                $rows[] = [$row['title'], $row['employee_name'], $row['taxpayer_name'], $row['job_type_name'], $row['target_value'], $row['actual_value'], $row['amount_collected'], $row['status']];
            }
        } elseif ($report === 'gps') {
            $headers = ['Judul', 'Pegawai', 'Wajib Pajak', 'Latitude', 'Longitude', 'Jarak (m)', 'Valid', 'Submit'];
            foreach ($data as $row) {
                $rows[] = [$row['title'], $row['employee_name'], $row['taxpayer_name'], $row['gps_latitude'], $row['gps_longitude'], $row['distance_meters'], $row['gps_valid'] ? 'Ya' : 'Tidak', $row['submitted_at']];
            }
        } elseif ($report === 'incentives') {
            $headers = ['Pegawai', 'Skor KPI', 'Proporsi', 'Dana Mingguan', 'Dana Bulanan', 'Total'];
            foreach ($data as $row) {
                $rows[] = [$row['employee_name'], $row['kpi_score'], $row['proportion'], $row['weekly_amount'], $row['monthly_amount'], $row['total_amount']];
            }
        } else {
            $headers = ['Periode', 'Total Tugas', 'Total Penagihan'];
            foreach ($data as $row) {
                $rows[] = [
                    $row['week_code'] ?? $row['month_code'] ?? '-',
                    $row['total_tasks'] ?? 0,
                    $row['total_collected'] ?? 0,
                ];
            }
        }

        return ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    }
}
