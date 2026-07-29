<?php
require __DIR__ . '/app/bootstrap.php';

$path = current_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    dispatch_request($path, $method, $service);
} catch (Throwable $exception) {
    http_response_code(500);
    render_page('Terjadi Kesalahan', function () use ($exception) {
        echo '<div class="card">';
        echo '<h2>Aplikasi gagal diproses</h2>';
        echo '<p>' . e($exception->getMessage()) . '</p>';
        echo '<p>Periksa konfigurasi database dan file upload, lalu coba lagi.</p>';
        echo '</div>';
    }, ['showNav' => current_user() !== null]);
}
