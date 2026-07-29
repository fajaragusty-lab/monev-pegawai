<?php
function dispatch_request(string $path, string $method, AppService $service): void
{
    if ($method === 'POST') {
        verify_csrf();
    }

    if ($path === '/' || $path === '/login') {
        if ($method === 'POST') {
            login_action();
        }
        login_page();
        return;
    }

    if ($path === '/logout') {
        global $auth;
        $auth->logout();
        flash('success', 'Anda sudah logout.');
        redirect_to('/login');
    }

    require_login();

    if ($path === '/dashboard') {
        dashboard_page($service);
        return;
    }

    if (preg_match('#^/master/([^/]+)/create$#', $path, $matches)) {
        require_admin();
        if ($method === 'POST') {
            master_save_action($service, $matches[1]);
        }
        master_form_page($service, $matches[1]);
        return;
    }

    if (preg_match('#^/master/([^/]+)/edit$#', $path, $matches)) {
        require_admin();
        $id = (int) request_value('id');
        if ($method === 'POST') {
            master_save_action($service, $matches[1], $id);
        }
        master_form_page($service, $matches[1], $id);
        return;
    }

    if (preg_match('#^/master/([^/]+)/delete$#', $path, $matches) && $method === 'POST') {
        require_admin();
        master_delete_action($service, $matches[1], (int) request_value('id'));
        return;
    }

    if (preg_match('#^/master/([^/]+)$#', $path, $matches)) {
        require_admin();
        master_list_page($service, $matches[1]);
        return;
    }

    if ($path === '/assignments') {
        assignments_page($service);
        return;
    }

    if ($path === '/assignments/create') {
        require_admin();
        if ($method === 'POST') {
            assignment_create_action($service);
        }
        assignment_form_page($service);
        return;
    }

    if ($path === '/assignments/show') {
        assignment_detail_page($service, (int) request_value('id'));
        return;
    }

    if ($path === '/field-tasks') {
        field_tasks_page($service);
        return;
    }

    if ($path === '/field-tasks/accept' && $method === 'POST') {
        field_accept_action($service, (int) request_value('id'));
        return;
    }

    if ($path === '/field-tasks/submit') {
        if ($method === 'POST') {
            field_submit_action($service, (int) request_value('id'));
        }
        field_submit_page($service, (int) request_value('id'));
        return;
    }

    if ($path === '/verification') {
        require_admin();
        verification_page($service);
        return;
    }

    if ($path === '/verification/show') {
        require_admin();
        verification_detail_page($service, (int) request_value('id'));
        return;
    }

    if (($path === '/verification/approve' || $path === '/verification/reject') && $method === 'POST') {
        require_admin();
        verification_action($service, (int) request_value('id'), str_ends_with($path, 'approve') ? 'approve' : 'reject');
        return;
    }

    if ($path === '/kpi') {
        if ($method === 'POST') {
            require_admin();
            kpi_recalculate_action($service);
        }
        kpi_page($service);
        return;
    }

    if ($path === '/rankings') {
        rankings_page($service);
        return;
    }

    if ($path === '/incentives') {
        if ($method === 'POST') {
            require_admin();
            $action = request_value('action');
            if ($action === 'save_fund') {
                incentive_save_fund_action($service);
            }
            if ($action === 'recalculate') {
                incentive_recalculate_action($service);
            }
        }
        incentives_page($service);
        return;
    }

    if ($path === '/reports') {
        reports_page($service);
        return;
    }

    if ($path === '/reports/export') {
        reports_export_action($service);
        return;
    }

    http_response_code(404);
    render_page('404', function () {
        echo '<div class="card"><h3>Halaman tidak ditemukan.</h3></div>';
    });
}

function login_page(): void
{
    if (current_user()) {
        redirect_to('/dashboard');
    }
    render_page('Login', function () {
        ?>
        <p class="muted">Login untuk masuk ke dashboard monitoring, penugasan, verifikasi, KPI, ranking, insentif, dan laporan.</p>
        <form method="post" action="<?= e(route_url('/login')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= e((string) old_input('username', '')) ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" type="submit">Masuk</button>
        </form>
        <hr style="margin:20px 0; border:none; border-top:1px solid #e5e7eb;">
        <small class="muted">Akun seed: admin / password123 dan petugas / password123</small>
        <?php
    }, ['showNav' => false]);
}

function login_action(): void
{
    global $auth;
    flash_input($_POST);
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        flash('error', 'Username dan password wajib diisi.');
        redirect_to('/login');
    }

    if (!$auth->attempt($username, $password)) {
        flash('error', 'Username atau password tidak valid.');
        redirect_to('/login');
    }

    clear_old_input();
    flash('success', 'Login berhasil.');
    redirect_to('/dashboard');
}

function dashboard_page(AppService $service): void
{
    $summary = $service->dashboardSummary(current_user());
    $assignments = array_slice($service->listAssignments(current_user()), 0, 5);
    render_page('Dashboard', function () use ($summary, $assignments) {
        echo '<div class="stats">';
        foreach ([
            'Pegawai' => $summary['pegawai'],
            'Wajib Pajak' => $summary['wajib_pajak'],
            'Total Penugasan' => $summary['penugasan'],
            'Menunggu Verifikasi' => $summary['menunggu_verifikasi'],
            'Disetujui' => $summary['disetujui'],
            'Total Insentif' => format_currency($summary['insentif_total']),
        ] as $label => $value) {
            echo '<div class="stat"><h3>' . e($label) . '</h3><strong>' . e((string) $value) . '</strong></div>';
        }
        echo '</div>';

        echo '<div class="grid grid-2">';
        echo '<div class="card"><h3>Siklus Operasional</h3><p class="muted">Master Data → Penugasan → Pelaksanaan → Verifikasi → KPI → Ranking → Insentif → Laporan.</p></div>';
        echo '<div class="card"><h3>Menu Cepat</h3><div class="list-inline">';
        foreach (nav_links() as [$path, $label]) {
            echo '<a class="btn light" href="' . e(route_url($path)) . '">' . e($label) . '</a>';
        }
        echo '</div></div>';
        echo '</div>';

        echo '<div class="card"><h3>Penugasan Terbaru</h3><div class="table-wrap"><table><thead><tr><th>Judul</th><th>Pegawai</th><th>Wajib Pajak</th><th>Status</th><th>Deadline</th><th></th></tr></thead><tbody>';
        foreach ($assignments as $row) {
            echo '<tr>';
            echo '<td>' . e($row['title']) . '</td>';
            echo '<td>' . e($row['employee_name']) . '</td>';
            echo '<td>' . e($row['taxpayer_name']) . '</td>';
            echo '<td>' . status_badge($row['status']) . '</td>';
            echo '<td>' . e(format_date($row['deadline_date'])) . '</td>';
            echo '<td><a href="' . e(route_url('/assignments/show', ['id' => $row['id']])) . '">Detail</a></td>';
            echo '</tr>';
        }
        if (!$assignments) {
            echo '<tr><td colspan="6">Belum ada data penugasan.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function master_list_page(AppService $service, string $slug): void
{
    $module = $service->module($slug);
    $rows = $service->moduleRecords($slug);
    $optionMaps = [];
    foreach ($module['fields'] as $field => $meta) {
        if (($meta['type'] ?? '') === 'select' && isset($meta['source'])) {
            $optionMaps[$field] = $service->selectOptions($meta['source'], $meta['source_label'] ?? 'name');
        }
        if (($meta['type'] ?? '') === 'select' && isset($meta['options'])) {
            $optionMaps[$field] = $meta['options'];
        }
    }

    render_page($module['title'], function () use ($service, $module, $rows, $slug, $optionMaps) {
        render_master_links($service);
        echo '<div class="card">';
        echo '<div class="topbar" style="margin-bottom:12px;"><h3 style="margin:0;">' . e($module['title']) . '</h3><a class="btn" href="' . e(route_url('/master/' . $slug . '/create')) . '">Tambah Data</a></div>';
        echo '<div class="table-wrap"><table><thead><tr>';
        foreach ($module['list'] as $column) {
            echo '<th>' . e($module['fields'][$column]['label'] ?? strtoupper($column)) . '</th>';
        }
        echo '<th>Aksi</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($module['list'] as $column) {
                $value = $row[$column] ?? '';
                if (isset($optionMaps[$column])) {
                    $value = $optionMaps[$column][(string) $value] ?? $value;
                }
                if ($column === 'status') {
                    echo '<td>' . status_badge((string) $row[$column]) . '</td>';
                    continue;
                }
                echo '<td>' . e((string) $value) . '</td>';
            }
            echo '<td><div class="list-inline">';
            echo '<a href="' . e(route_url('/master/' . $slug . '/edit', ['id' => $row['id']])) . '">Edit</a>';
            echo '<form class="inline" method="post" action="' . e(route_url('/master/' . $slug . '/delete', ['id' => $row['id']])) . '" onsubmit="return confirm(\'Hapus data ini?\')">' . csrf_field() . '<button class="btn danger" type="submit">Hapus</button></form>';
            echo '</div></td>';
            echo '</tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="99">Belum ada data.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function master_form_page(AppService $service, string $slug, ?int $id = null): void
{
    $module = $service->module($slug);
    $record = $id ? $service->moduleRecord($slug, $id) : [];
    if ($id && !$record) {
        flash('error', 'Data tidak ditemukan.');
        redirect_to('/master/' . $slug);
    }
    $values = $_SESSION['_old'] ?? $record;
    render_page(($id ? 'Edit ' : 'Tambah ') . $module['title'], function () use ($service, $module, $slug, $id, $values) {
        render_master_links($service);
        echo '<div class="card">';
        echo '<form method="post" action="' . e(route_url('/master/' . $slug . '/' . ($id ? 'edit' : 'create'), $id ? ['id' => $id] : [])) . '">';
        echo csrf_field();
        render_form_fields($module['fields'], $values, $service, $id !== null);
        echo '<div class="list-inline"><button class="btn" type="submit">Simpan</button><a class="btn light" href="' . e(route_url('/master/' . $slug)) . '">Kembali</a></div>';
        echo '</form></div>';
    });
    clear_old_input();
}

function master_save_action(AppService $service, string $slug, ?int $id = null): void
{
    try {
        flash_input($_POST);
        $service->saveModuleRecord($slug, $_POST, $id);
        clear_old_input();
        flash('success', 'Data berhasil disimpan.');
        redirect_to('/master/' . $slug);
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('/master/' . $slug . '/' . ($id ? 'edit' : 'create'), $id ? ['id' => $id] : []);
    }
}

function master_delete_action(AppService $service, string $slug, int $id): void
{
    try {
        $service->deleteModuleRecord($slug, $id);
        flash('success', 'Data berhasil dihapus.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/master/' . $slug);
}

function assignments_page(AppService $service): void
{
    $periodId = (int) request_value('period_id', 0);
    $periods = $service->periods();
    $rows = $service->listAssignments(current_user(), $periodId ?: null);
    render_page('Penugasan', function () use ($rows, $periods, $periodId) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/assignments')) . '"><div class="form-grid"><div class="form-group"><label>Filter Periode</label><select name="period_id"><option value="">Semua Periode</option>';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Filter</button>';
        if (is_admin()) {
            echo ' <a class="btn" href="' . e(route_url('/assignments/create')) . '">Buat Penugasan</a>';
        }
        echo '</form></div>';

        echo '<div class="card"><div class="table-wrap"><table><thead><tr><th>Judul</th><th>Periode</th><th>Pegawai</th><th>Wajib Pajak</th><th>Deadline</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . e($row['title']) . '</td><td>' . e($row['period_name']) . '</td><td>' . e($row['employee_name']) . '</td><td>' . e($row['taxpayer_name']) . '</td><td>' . e(format_date($row['deadline_date'])) . '</td><td>' . status_badge($row['status']) . '</td><td><a href="' . e(route_url('/assignments/show', ['id' => $row['id']])) . '">Detail</a></td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="7">Belum ada data penugasan.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function assignment_form_page(AppService $service): void
{
    $values = $_SESSION['_old'] ?? [];
    $periods = $service->periods();
    $employees = $service->employeesByRole('petugas');
    $taxpayers = $service->activeTaxpayers();
    $jobTypes = $service->activeJobTypes();
    render_page('Buat Penugasan', function () use ($values, $periods, $employees, $taxpayers, $jobTypes) {
        echo '<div class="card"><form method="post" action="' . e(route_url('/assignments/create')) . '">' . csrf_field();
        echo '<div class="form-grid">';
        echo '<div class="form-group"><label>Periode</label><select name="period_id" required><option value="">-- pilih --</option>';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((string) $period['id'] === (string) ($values['period_id'] ?? '') ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>Judul Tugas</label><input type="text" name="title" value="' . e((string) ($values['title'] ?? '')) . '" required></div>';
        echo '<div class="form-group"><label>Pegawai</label><select name="employee_id" required><option value="">-- pilih --</option>';
        foreach ($employees as $employee) {
            echo '<option value="' . e((string) $employee['id']) . '" ' . ((string) $employee['id'] === (string) ($values['employee_id'] ?? '') ? 'selected' : '') . '>' . e($employee['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>Wajib Pajak</label><select name="taxpayer_id" required><option value="">-- pilih --</option>';
        foreach ($taxpayers as $taxpayer) {
            echo '<option value="' . e((string) $taxpayer['id']) . '" ' . ((string) $taxpayer['id'] === (string) ($values['taxpayer_id'] ?? '') ? 'selected' : '') . '>' . e($taxpayer['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>Jenis Pekerjaan</label><select name="job_type_id" required><option value="">-- pilih --</option>';
        foreach ($jobTypes as $jobType) {
            echo '<option value="' . e((string) $jobType['id']) . '" ' . ((string) $jobType['id'] === (string) ($values['job_type_id'] ?? '') ? 'selected' : '') . '>' . e($jobType['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>Target</label><input type="number" step="0.01" name="target_value" value="' . e((string) ($values['target_value'] ?? '1')) . '"></div>';
        echo '<div class="form-group"><label>Deadline</label><input type="date" name="deadline_date" value="' . e((string) ($values['deadline_date'] ?? '')) . '" required></div>';
        echo '<div class="form-group"><label>Prioritas</label><select name="priority"><option value="low">Low</option><option value="medium" ' . (($values['priority'] ?? 'medium') === 'medium' ? 'selected' : '') . '>Medium</option><option value="high" ' . (($values['priority'] ?? '') === 'high' ? 'selected' : '') . '>High</option></select></div>';
        echo '<div class="form-group"><label>Catatan</label><textarea name="notes">' . e((string) ($values['notes'] ?? '')) . '</textarea></div>';
        echo '</div><button class="btn" type="submit">Simpan Penugasan</button></form></div>';
    });
    clear_old_input();
}

function assignment_create_action(AppService $service): void
{
    try {
        flash_input($_POST);
        $service->createAssignment($_POST, current_user()['id']);
        clear_old_input();
        flash('success', 'Penugasan berhasil disimpan.');
        redirect_to('/assignments');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('/assignments/create');
    }
}

function assignment_detail_page(AppService $service, int $id): void
{
    $assignment = $service->getAssignment($id, current_user());
    if (!$assignment) {
        flash('error', 'Penugasan tidak ditemukan.');
        redirect_to('/assignments');
    }

    render_page('Detail Penugasan', function () use ($assignment) {
        echo '<div class="grid grid-2">';
        echo '<div class="card"><h3>Ringkasan Tugas</h3><p><strong>Judul:</strong> ' . e($assignment['title']) . '</p><p><strong>Periode:</strong> ' . e($assignment['period_name']) . '</p><p><strong>Status:</strong> ' . status_badge($assignment['status']) . '</p><p><strong>Deadline:</strong> ' . e(format_date($assignment['deadline_date'])) . '</p><p><strong>Jenis Pekerjaan:</strong> ' . e($assignment['job_type_name']) . '</p><p><strong>Pegawai:</strong> ' . e($assignment['employee_name']) . '</p><p><strong>Target:</strong> ' . e((string) $assignment['target_value']) . '</p><p><strong>Catatan:</strong><br>' . nl2br(e((string) $assignment['notes'])) . '</p></div>';
        echo '<div class="card"><h3>Wajib Pajak</h3><p><strong>Nama:</strong> ' . e($assignment['taxpayer_name']) . '</p><p><strong>Alamat:</strong><br>' . nl2br(e((string) $assignment['taxpayer_address'])) . '</p><p><strong>Radius Validasi:</strong> ' . e((string) ($assignment['radius_meters'] ?: app_config('app.default_radius_meters'))) . ' meter</p><p><strong>Verifier:</strong> ' . e((string) ($assignment['verifier_name'] ?: '-')) . '</p><p><strong>Catatan Verifikasi:</strong><br>' . nl2br(e((string) ($assignment['verification_notes'] ?: '-'))) . '</p></div>';
        echo '</div>';

        echo '<div class="card"><h3>Hasil Lapangan</h3><div class="table-wrap"><table><tbody>';
        foreach ([
            'GPS Latitude' => $assignment['gps_latitude'] ?: '-',
            'GPS Longitude' => $assignment['gps_longitude'] ?: '-',
            'Jarak (meter)' => $assignment['distance_meters'] ? number_format((float) $assignment['distance_meters'], 2, ',', '.') : '-',
            'GPS Valid' => $assignment['gps_valid'] ? 'Ya' : 'Belum',
            'Realisasi' => $assignment['actual_value'] ?: '-',
            'Nominal Penagihan' => $assignment['amount_collected'] ? format_currency((float) $assignment['amount_collected']) : '-',
            'Kelengkapan Dokumen' => $assignment['document_complete'] ? 'Lengkap' : 'Belum',
            'Submit' => $assignment['submitted_at'] ? format_date($assignment['submitted_at'], 'd-m-Y H:i') : '-',
        ] as $label => $value) {
            echo '<tr><th>' . e($label) . '</th><td>' . e((string) $value) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        if ($assignment['location_photo_path']) {
            echo '<p><a target="_blank" href="' . e(base_url($assignment['location_photo_path'])) . '">Lihat Foto Lokasi</a></p>';
        }
        if ($assignment['evidence_file_path']) {
            echo '<p><a target="_blank" href="' . e(base_url($assignment['evidence_file_path'])) . '">Lihat File Bukti</a></p>';
        }
        echo '</div>';
    });
}

function field_tasks_page(AppService $service): void
{
    $rows = $service->listAssignments(current_user());
    render_page('Pelaksanaan Lapangan', function () use ($rows) {
        echo '<div class="card"><h3>Daftar Tugas</h3><p class="muted">Terima tugas, buka detail, validasi GPS, unggah foto lokasi, isi hasil, unggah bukti, lalu submit.</p><div class="table-wrap"><table><thead><tr><th>Judul</th><th>Wajib Pajak</th><th>Status</th><th>Deadline</th><th>Aksi</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . e($row['title']) . '</td><td>' . e($row['taxpayer_name']) . '</td><td>' . status_badge($row['status']) . '</td><td>' . e(format_date($row['deadline_date'])) . '</td><td><div class="list-inline">';
            echo '<a href="' . e(route_url('/assignments/show', ['id' => $row['id']])) . '">Detail</a>';
            if (!is_admin() && in_array($row['status'], ['assigned', 'rejected'], true)) {
                echo '<form class="inline" method="post" action="' . e(route_url('/field-tasks/accept', ['id' => $row['id']])) . '">' . csrf_field() . '<button class="btn success" type="submit">Terima Tugas</button></form>';
            }
            if (!is_admin() && in_array($row['status'], ['accepted', 'rejected'], true)) {
                echo '<a class="btn warning" href="' . e(route_url('/field-tasks/submit', ['id' => $row['id']])) . '">Input Hasil</a>';
            }
            echo '</div></td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="5">Tidak ada tugas untuk akun ini.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function field_accept_action(AppService $service, int $id): void
{
    try {
        $service->acceptAssignment($id, current_user());
        flash('success', 'Tugas berhasil diterima.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/field-tasks');
}

function field_submit_page(AppService $service, int $id): void
{
    if (is_admin()) {
        flash('error', 'Halaman ini untuk petugas.');
        redirect_to('/assignments');
    }
    $assignment = $service->getAssignment($id, current_user());
    if (!$assignment) {
        flash('error', 'Tugas tidak ditemukan.');
        redirect_to('/field-tasks');
    }
    $values = $_SESSION['_old'] ?? $assignment;
    render_page('Submit Hasil Lapangan', function () use ($assignment, $values) {
        echo '<div class="card"><h3>' . e($assignment['title']) . '</h3><p><strong>Wajib Pajak:</strong> ' . e($assignment['taxpayer_name']) . '</p><p><strong>Alamat:</strong> ' . e($assignment['taxpayer_address']) . '</p><p><strong>Radius Validasi:</strong> ' . e((string) ($assignment['radius_meters'] ?: app_config('app.default_radius_meters'))) . ' meter</p></div>';
        echo '<div class="card"><form method="post" enctype="multipart/form-data" action="' . e(route_url('/field-tasks/submit', ['id' => $assignment['id']])) . '">' . csrf_field();
        echo '<div class="form-grid">';
        echo '<div class="form-group"><label>Latitude GPS</label><input type="number" step="0.0000001" name="gps_latitude" value="' . e((string) ($values['gps_latitude'] ?? '')) . '" required></div>';
        echo '<div class="form-group"><label>Longitude GPS</label><input type="number" step="0.0000001" name="gps_longitude" value="' . e((string) ($values['gps_longitude'] ?? '')) . '" required></div>';
        echo '<div class="form-group"><label>Realisasi</label><input type="number" step="0.01" name="actual_value" value="' . e((string) ($values['actual_value'] ?? '')) . '" required></div>';
        echo '<div class="form-group"><label>Nominal Penagihan Berhasil</label><input type="number" step="0.01" name="amount_collected" value="' . e((string) ($values['amount_collected'] ?? '0')) . '"></div>';
        echo '<div class="form-group"><label>Foto Lokasi</label><input type="file" name="location_photo" accept=".jpg,.jpeg,.png" required></div>';
        echo '<div class="form-group"><label>Upload Bukti</label><input type="file" name="evidence_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required></div>';
        echo '<div class="form-group" style="grid-column:1/-1;"><label>Hasil / Catatan</label><textarea name="result_notes" required>' . e((string) ($values['result_notes'] ?? '')) . '</textarea></div>';
        echo '<div class="form-group"><label><input type="checkbox" name="document_complete" value="1" ' . (!empty($values['document_complete']) ? 'checked' : '') . '> Kelengkapan dokumen sudah lengkap</label></div>';
        echo '</div><button class="btn" type="submit">Submit Hasil</button></form></div>';
    });
    clear_old_input();
}

function field_submit_action(AppService $service, int $id): void
{
    try {
        flash_input($_POST);
        $service->submitAssignment($id, current_user(), $_POST, $_FILES);
        clear_old_input();
        flash('success', 'Hasil pelaksanaan berhasil dikirim untuk verifikasi.');
        redirect_to('/field-tasks');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        redirect_to('/field-tasks/submit', ['id' => $id]);
    }
}

function verification_page(AppService $service): void
{
    $periodId = (int) request_value('period_id', 0);
    $rows = $service->verificationList($periodId ?: null);
    $periods = $service->periods();
    render_page('Verifikasi', function () use ($rows, $periods, $periodId) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/verification')) . '"><div class="form-grid"><div class="form-group"><label>Filter Periode</label><select name="period_id"><option value="">Semua Periode</option>';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Filter</button></form></div>';
        echo '<div class="card"><div class="table-wrap"><table><thead><tr><th>Judul</th><th>Pegawai</th><th>Wajib Pajak</th><th>Status</th><th>Submit</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . e($row['title']) . '</td><td>' . e($row['employee_name']) . '</td><td>' . e($row['taxpayer_name']) . '</td><td>' . status_badge($row['status']) . '</td><td>' . e(format_date($row['submitted_at'], 'd-m-Y H:i')) . '</td><td><a href="' . e(route_url('/verification/show', ['id' => $row['id']])) . '">Review</a></td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="6">Belum ada hasil yang menunggu verifikasi.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function verification_detail_page(AppService $service, int $id): void
{
    $assignment = $service->getAssignment($id, null);
    if (!$assignment || $assignment['status'] !== 'submitted') {
        flash('error', 'Data verifikasi tidak ditemukan.');
        redirect_to('/verification');
    }

    render_page('Review Verifikasi', function () use ($assignment) {
        echo '<div class="grid grid-2">';
        echo '<div class="card"><h3>Data Tugas</h3><p><strong>Judul:</strong> ' . e($assignment['title']) . '</p><p><strong>Pegawai:</strong> ' . e($assignment['employee_name']) . '</p><p><strong>Wajib Pajak:</strong> ' . e($assignment['taxpayer_name']) . '</p><p><strong>Deadline:</strong> ' . e(format_date($assignment['deadline_date'])) . '</p><p><strong>Submit:</strong> ' . e(format_date($assignment['submitted_at'], 'd-m-Y H:i')) . '</p></div>';
        echo '<div class="card"><h3>Pemeriksaan Kelengkapan</h3><p><strong>GPS Valid:</strong> ' . e($assignment['gps_valid'] ? 'Ya' : 'Tidak') . '</p><p><strong>Jarak:</strong> ' . e(number_format((float) $assignment['distance_meters'], 2, ',', '.')) . ' meter</p><p><strong>Dokumen:</strong> ' . e($assignment['document_complete'] ? 'Lengkap' : 'Belum lengkap') . '</p><p><strong>Hasil:</strong><br>' . nl2br(e((string) $assignment['result_notes'])) . '</p><p><a target="_blank" href="' . e(base_url($assignment['location_photo_path'])) . '">Foto Lokasi</a> | <a target="_blank" href="' . e(base_url($assignment['evidence_file_path'])) . '">File Bukti</a></p></div>';
        echo '</div>';
        echo '<div class="card"><div class="grid grid-2">';
        echo '<form method="post" action="' . e(route_url('/verification/approve', ['id' => $assignment['id']])) . '">' . csrf_field() . '<div class="form-group"><label>Catatan Admin (opsional)</label><textarea name="verification_notes"></textarea></div><button class="btn success" type="submit">Setujui</button></form>';
        echo '<form method="post" action="' . e(route_url('/verification/reject', ['id' => $assignment['id']])) . '">' . csrf_field() . '<div class="form-group"><label>Catatan Penolakan</label><textarea name="verification_notes" required></textarea></div><button class="btn danger" type="submit">Tolak</button></form>';
        echo '</div></div>';
    });
}

function verification_action(AppService $service, int $id, string $action): void
{
    try {
        $notes = trim((string) ($_POST['verification_notes'] ?? ''));
        if ($action === 'reject' && $notes === '') {
            throw new RuntimeException('Catatan penolakan wajib diisi.');
        }
        $service->verifyAssignment($id, $action, $notes, current_user()['id']);
        flash('success', $action === 'approve' ? 'Hasil lapangan disetujui.' : 'Hasil lapangan ditolak.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/verification');
}

function selected_period(AppService $service): int
{
    $periodId = (int) request_value('period_id', 0);
    if ($periodId > 0) {
        return $periodId;
    }
    $periods = $service->periods();
    return (int) ($periods[0]['id'] ?? 0);
}

function kpi_page(AppService $service): void
{
    $periodId = selected_period($service);
    $periods = $service->periods();
    $scores = $periodId ? $service->kpiScores($periodId) : [];
    render_page('Perhitungan KPI', function () use ($periodId, $periods, $scores) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/kpi')) . '"><div class="form-grid"><div class="form-group"><label>Pilih Periode</label><select name="period_id">';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Tampilkan</button></form>';
        if (is_admin() && $periodId) {
            echo '<form method="post" class="inline" action="' . e(route_url('/kpi', ['period_id' => $periodId])) . '">' . csrf_field() . '<button class="btn" type="submit">Hitung Ulang KPI</button></form>';
        }
        echo '</div>';

        echo '<div class="card"><div class="table-wrap"><table><thead><tr><th>Pegawai</th><th>Target</th><th>Realisasi</th><th>Berhasil</th><th>Verval</th><th>GPS</th><th>Tepat Waktu</th><th>Dokumen</th><th>Skor</th></tr></thead><tbody>';
        foreach ($scores as $row) {
            echo '<tr><td>' . e($row['employee_name']) . '</td><td>' . e($row['target_count'] . ' (' . $row['target_ratio'] . '%)') . '</td><td>' . e($row['realization_count'] . ' (' . $row['realization_ratio'] . '%)') . '</td><td>' . e($row['collection_success_count'] . ' (' . $row['collection_success_ratio'] . '%)') . '</td><td>' . e($row['verification_count'] . ' (' . $row['verification_ratio'] . '%)') . '</td><td>' . e($row['gps_valid_count'] . ' (' . $row['gps_valid_ratio'] . '%)') . '</td><td>' . e($row['on_time_count'] . ' (' . $row['on_time_ratio'] . '%)') . '</td><td>' . e($row['document_complete_count'] . ' (' . $row['document_complete_ratio'] . '%)') . '</td><td><strong>' . e((string) $row['score_total']) . '</strong></td></tr>';
        }
        if (!$scores) {
            echo '<tr><td colspan="9">Belum ada hasil KPI untuk periode ini.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function kpi_recalculate_action(AppService $service): void
{
    $periodId = selected_period($service);
    try {
        if ($periodId <= 0) {
            throw new RuntimeException('Periode belum tersedia.');
        }
        $service->recalculateKpi($periodId);
        flash('success', 'Perhitungan KPI berhasil dijalankan.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/kpi', ['period_id' => $periodId]);
}

function rankings_page(AppService $service): void
{
    $periodId = selected_period($service);
    $periods = $service->periods();
    $rows = $periodId ? $service->rankings($periodId) : [];
    render_page('Ranking Pegawai', function () use ($periods, $periodId, $rows) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/rankings')) . '"><div class="form-grid"><div class="form-group"><label>Pilih Periode</label><select name="period_id">';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Tampilkan</button></form></div>';
        echo '<div class="card"><div class="table-wrap"><table><thead><tr><th>Rank</th><th>Pegawai</th><th>Skor KPI</th><th>Visual</th></tr></thead><tbody>';
        $max = $rows[0]['score_total'] ?? 0;
        foreach ($rows as $row) {
            echo '<tr><td>#' . e((string) $row['ranking']) . '</td><td>' . e($row['employee_name']) . '</td><td><strong>' . e((string) $row['score_total']) . '</strong></td><td>' . report_bar((float) $row['score_total'], (float) $max) . '</td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="4">Ranking belum tersedia. Jalankan perhitungan KPI terlebih dahulu.</td></tr>';
        }
        echo '</tbody></table></div></div>';
    });
}

function incentives_page(AppService $service): void
{
    $periodId = selected_period($service);
    $periods = $service->periods();
    $fund = $periodId ? $service->incentiveFund($periodId) : null;
    $rows = $periodId ? $service->incentiveResults($periodId) : [];
    $setting = $service->incentiveSetting();

    render_page('Insentif', function () use ($periods, $periodId, $fund, $rows, $setting) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/incentives')) . '"><div class="form-grid"><div class="form-group"><label>Pilih Periode</label><select name="period_id">';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Tampilkan</button></form></div>';
        echo '<div class="grid grid-2">';
        echo '<div class="card"><h3>Dana Insentif</h3><p class="muted">Dana dibagi proporsional terhadap total KPI pegawai yang memenuhi minimum KPI.</p>';
        if (is_admin()) {
            echo '<form method="post" action="' . e(route_url('/incentives', ['period_id' => $periodId])) . '">' . csrf_field() . '<input type="hidden" name="action" value="save_fund"><div class="form-grid"><div class="form-group"><label>Dana Mingguan</label><input type="number" step="0.01" name="weekly_fund" value="' . e((string) ($fund['weekly_fund'] ?? '0')) . '" required></div><div class="form-group"><label>Dana Bulanan</label><input type="number" step="0.01" name="monthly_fund" value="' . e((string) ($fund['monthly_fund'] ?? '0')) . '" required></div></div><button class="btn" type="submit">Simpan Dana</button></form>';
            echo '<form method="post" class="inline" action="' . e(route_url('/incentives', ['period_id' => $periodId])) . '">' . csrf_field() . '<input type="hidden" name="action" value="recalculate"><button class="btn success" type="submit">Hitung Insentif</button></form>';
        } else {
            echo '<p><strong>Dana Mingguan:</strong> ' . format_currency((float) ($fund['weekly_fund'] ?? 0)) . '</p><p><strong>Dana Bulanan:</strong> ' . format_currency((float) ($fund['monthly_fund'] ?? 0)) . '</p>';
        }
        echo '<p><strong>Setting aktif:</strong> ' . e($setting['name'] ?? '-') . ' (Minimum KPI ' . e((string) ($setting['minimum_kpi'] ?? 0)) . ', multiplier ' . e((string) ($setting['bonus_multiplier'] ?? 1)) . ')</p>';
        echo '</div>';
        echo '<div class="card"><h3>Hasil Proporsi</h3><div class="table-wrap"><table><thead><tr><th>Pegawai</th><th>Skor KPI</th><th>Proporsi</th><th>Mingguan</th><th>Bulanan</th><th>Total</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_admin() && (int) $row['employee_id'] !== (int) current_user()['id']) {
                continue;
            }
            echo '<tr><td>' . e($row['employee_name']) . '</td><td>' . e((string) $row['kpi_score']) . '</td><td>' . e(number_format((float) $row['proportion'] * 100, 2, ',', '.') . '%') . '</td><td>' . format_currency((float) $row['weekly_amount']) . '</td><td>' . format_currency((float) $row['monthly_amount']) . '</td><td><strong>' . format_currency((float) $row['total_amount']) . '</strong></td></tr>';
        }
        if (!$rows) {
            echo '<tr><td colspan="6">Hasil insentif belum tersedia.</td></tr>';
        }
        echo '</tbody></table></div></div></div>';
    });
}

function incentive_save_fund_action(AppService $service): void
{
    $periodId = selected_period($service);
    try {
        $service->saveIncentiveFund($periodId, (float) ($_POST['weekly_fund'] ?? 0), (float) ($_POST['monthly_fund'] ?? 0));
        flash('success', 'Dana insentif berhasil disimpan.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/incentives', ['period_id' => $periodId]);
}

function incentive_recalculate_action(AppService $service): void
{
    $periodId = selected_period($service);
    try {
        $service->recalculateIncentives($periodId);
        flash('success', 'Perhitungan insentif berhasil dijalankan.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }
    redirect_to('/incentives', ['period_id' => $periodId]);
}

function reports_page(AppService $service): void
{
    $periodId = selected_period($service);
    $periods = $service->periods();
    $report = request_value('report', 'kpi');
    $rows = $periodId ? $service->reportData($report, $periodId, current_user()) : [];
    render_page('Laporan', function () use ($service, $periodId, $periods, $report, $rows) {
        echo '<div class="card"><form method="get" action="' . e(route_url('/reports')) . '"><div class="form-grid">';
        echo '<div class="form-group"><label>Periode</label><select name="period_id">';
        foreach ($periods as $period) {
            echo '<option value="' . e((string) $period['id']) . '" ' . ((int) $period['id'] === $periodId ? 'selected' : '') . '>' . e($period['name']) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="form-group"><label>Jenis Laporan</label><select name="report">';
        foreach (['kpi' => 'Laporan KPI', 'collections' => 'Laporan Penagihan', 'gps' => 'Laporan GPS', 'incentives' => 'Laporan Insentif', 'weekly' => 'Grafik Mingguan', 'monthly' => 'Grafik Bulanan'] as $key => $label) {
            echo '<option value="' . e($key) . '" ' . ($report === $key ? 'selected' : '') . '>' . e($label) . '</option>';
        }
        echo '</select></div></div><button class="btn light" type="submit">Tampilkan</button>';
        if ($periodId) {
            echo ' <a class="btn" href="' . e(route_url('/reports/export', ['period_id' => $periodId, 'report' => $report, 'type' => 'pdf'])) . '">Export PDF</a>';
            echo ' <a class="btn success" href="' . e(route_url('/reports/export', ['period_id' => $periodId, 'report' => $report, 'type' => 'excel'])) . '">Export Excel</a>';
        }
        echo '</form></div>';

        echo '<div class="card">';
        if (in_array($report, ['weekly', 'monthly'], true)) {
            $max = 0;
            foreach ($rows as $row) {
                $max = max($max, (float) $row['total_collected']);
            }
            echo '<div class="table-wrap"><table><thead><tr><th>Periode</th><th>Total Tugas</th><th>Total Penagihan</th><th>Grafik</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                $periodLabel = $row['week_code'] ?? $row['month_code'] ?? '-';
                echo '<tr><td>' . e($periodLabel) . '</td><td>' . e((string) $row['total_tasks']) . '</td><td>' . format_currency((float) $row['total_collected']) . '</td><td>' . report_bar((float) $row['total_collected'], $max) . '</td></tr>';
            }
            if (!$rows) {
                echo '<tr><td colspan="4">Belum ada data grafik.</td></tr>';
            }
            echo '</tbody></table></div>';
        } elseif ($report === 'kpi') {
            echo '<div class="table-wrap"><table><thead><tr><th>Pegawai</th><th>Skor</th><th>Target</th><th>Realisasi</th><th>GPS Valid</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>' . e($row['employee_name']) . '</td><td>' . e((string) $row['score_total']) . '</td><td>' . e((string) $row['target_count']) . '</td><td>' . e((string) $row['realization_count']) . '</td><td>' . e((string) $row['gps_valid_count']) . '</td></tr>';
            }
            if (!$rows) {
                echo '<tr><td colspan="5">Belum ada data KPI.</td></tr>';
            }
            echo '</tbody></table></div>';
        } elseif ($report === 'collections') {
            echo '<div class="table-wrap"><table><thead><tr><th>Judul</th><th>Pegawai</th><th>Wajib Pajak</th><th>Penagihan</th><th>Status</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>' . e($row['title']) . '</td><td>' . e($row['employee_name']) . '</td><td>' . e($row['taxpayer_name']) . '</td><td>' . format_currency((float) $row['amount_collected']) . '</td><td>' . status_badge($row['status']) . '</td></tr>';
            }
            if (!$rows) {
                echo '<tr><td colspan="5">Belum ada data penagihan.</td></tr>';
            }
            echo '</tbody></table></div>';
        } elseif ($report === 'gps') {
            echo '<div class="table-wrap"><table><thead><tr><th>Judul</th><th>Pegawai</th><th>Jarak</th><th>Valid</th><th>Submit</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr><td>' . e($row['title']) . '</td><td>' . e($row['employee_name']) . '</td><td>' . e(number_format((float) $row['distance_meters'], 2, ',', '.')) . ' m</td><td>' . e($row['gps_valid'] ? 'Ya' : 'Tidak') . '</td><td>' . e(format_date($row['submitted_at'], 'd-m-Y H:i')) . '</td></tr>';
            }
            if (!$rows) {
                echo '<tr><td colspan="5">Belum ada data GPS.</td></tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Pegawai</th><th>Skor KPI</th><th>Proporsi</th><th>Total Insentif</th></tr></thead><tbody>';
            foreach ($rows as $row) {
                if (!is_admin() && (int) $row['employee_id'] !== (int) current_user()['id']) {
                    continue;
                }
                echo '<tr><td>' . e($row['employee_name']) . '</td><td>' . e((string) $row['kpi_score']) . '</td><td>' . e(number_format((float) $row['proportion'] * 100, 2, ',', '.') . '%') . '</td><td>' . format_currency((float) $row['total_amount']) . '</td></tr>';
            }
            if (!$rows) {
                echo '<tr><td colspan="4">Belum ada data insentif.</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';

        echo '<div class="card"><h3>Dashboard Monitoring</h3><div class="list-inline">';
        foreach (['Master Data', 'Penugasan', 'Pelaksanaan Lapangan', 'Verifikasi', 'Perhitungan KPI', 'Ranking Pegawai', 'Insentif', 'Laporan'] as $step) {
            echo '<span class="btn light">' . e($step) . '</span>';
        }
        echo '</div></div>';
    });
}

function reports_export_action(AppService $service): void
{
    $periodId = (int) request_value('period_id', 0);
    $report = (string) request_value('report', 'kpi');
    $type = (string) request_value('type', 'pdf');
    if ($periodId <= 0) {
        flash('error', 'Periode laporan belum dipilih.');
        redirect_to('/reports');
    }
    $payload = $service->exportRows($report, $periodId, current_user());
    if ($type === 'excel') {
        export_csv($payload);
    }
    export_simple_pdf($payload);
}
