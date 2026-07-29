<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();
$assignments = $pdo->query(
    'SELECT a.id, u.name AS petugas, t.name AS wajib_pajak, a.target_amount
     FROM assignments a
     INNER JOIN users u ON u.id = a.petugas_id
     INNER JOIN taxpayers t ON t.id = a.taxpayer_id
     ORDER BY a.id DESC'
)->fetchAll();

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Hasil Petugas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; max-width: 900px; }
        label { display: block; margin-top: 12px; }
        input, select { width: 100%; padding: 8px; margin-top: 4px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .alert { padding: 10px; border-radius: 6px; margin: 10px 0; }
        .ok { background: #e8f7e8; color: #125c12; }
        .err { background: #fde8e8; color: #861a1a; }
        .hint { color: #666; font-size: 0.9rem; margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Input Hasil Petugas</h1>
    <a href="/public/index.php">← Kembali ke dashboard</a>

    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars((string) $message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= htmlspecialchars((string) $error) ?></div><?php endif; ?>

    <form method="post" action="/public/save_hasil.php" id="hasilForm">
        <label>Tugas
            <select name="assignment_id" required>
                <option value="">Pilih tugas</option>
                <?php foreach ($assignments as $a): ?>
                    <option value="<?= (int) $a['id'] ?>">
                        #<?= (int) $a['id'] ?> - <?= htmlspecialchars((string) $a['petugas']) ?> / <?= htmlspecialchars((string) $a['wajib_pajak']) ?> (Target <?= number_format((float) $a['target_amount'], 0, ',', '.') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="row">
            <label>Realisasi
                <input type="number" name="realisasi_amount" min="0" required>
            </label>
            <label>Penagihan Berhasil
                <input type="number" name="penagihan_berhasil" min="0" required>
            </label>
        </div>

        <div class="row">
            <label>Kelengkapan Dokumen (0-100)
                <input type="number" name="document_completeness" min="0" max="100" required>
            </label>
            <label>Verifikasi Lapangan Selesai
                <select name="verval_complete" required>
                    <option value="1">Ya</option>
                    <option value="0">Tidak</option>
                </select>
            </label>
        </div>

        <div class="row">
            <label>Latitude (otomatis)
                <input type="text" name="latitude" id="latitude" readonly required>
            </label>
            <label>Longitude (otomatis)
                <input type="text" name="longitude" id="longitude" readonly required>
            </label>
        </div>

        <input type="hidden" name="accuracy" id="accuracy">

        <p class="hint" id="geoHint">Meminta akses GPS browser...</p>

        <button type="submit" id="submitBtn" disabled>Submit Hasil</button>
    </form>

    <script>
        const lat = document.getElementById('latitude');
        const lon = document.getElementById('longitude');
        const accuracy = document.getElementById('accuracy');
        const submitBtn = document.getElementById('submitBtn');
        const geoHint = document.getElementById('geoHint');

        function lockSubmitWithMessage(message) {
            submitBtn.disabled = true;
            geoHint.textContent = message;
            geoHint.style.color = '#861a1a';
        }

        function enableSubmitWithMessage(message) {
            submitBtn.disabled = false;
            geoHint.textContent = message;
            geoHint.style.color = '#125c12';
        }

        function captureLocation() {
            if (!navigator.geolocation) {
                lockSubmitWithMessage('Browser tidak mendukung geolocation. Submit diblokir.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function (position) {
                    lat.value = position.coords.latitude.toFixed(7);
                    lon.value = position.coords.longitude.toFixed(7);
                    accuracy.value = position.coords.accuracy ? position.coords.accuracy.toFixed(2) : '';
                    enableSubmitWithMessage('Lokasi berhasil ditangkap otomatis. Anda dapat submit.');
                },
                function () {
                    lockSubmitWithMessage('Izin GPS ditolak/tidak tersedia. Aktifkan GPS browser untuk submit hasil.');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        }

        document.getElementById('hasilForm').addEventListener('submit', function (e) {
            if (!lat.value || !lon.value) {
                e.preventDefault();
                lockSubmitWithMessage('Koordinat belum tersedia. Izinkan GPS agar submit dapat dilakukan.');
            }
        });

        captureLocation();
    </script>
</body>
</html>
