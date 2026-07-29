<?php
function app_config(?string $key = null, mixed $default = null): mixed
{
    global $appConfig;
    if ($key === null) {
        return $appConfig;
    }

    $value = $appConfig;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base && $base !== '/' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return $path === '' ? '/' : $path;
}

function base_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '\\') {
        $base = '';
    }
    $final = $base;
    if ($path !== '') {
        $final .= '/' . ltrim($path, '/');
    }
    return $final === '' ? '/' : $final;
}

function route_url(string $path, array $params = []): string
{
    $url = base_url(ltrim($path, '/'));
    if ($params) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function redirect_to(string $path, array $params = []): never
{
    header('Location: ' . route_url($path, $params));
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf'] ?? '';
        if (!$token || !hash_equals(csrf_token(), $token)) {
            http_response_code(419);
            exit('CSRF token tidak valid.');
        }
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['_flashes'] ?? [];
    unset($_SESSION['_flashes']);
    return $flashes;
}

function old_input(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function flash_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function current_user(): ?array
{
    global $auth;
    return $auth->user();
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Silakan login terlebih dahulu.');
        redirect_to('/login');
    }
}

function require_admin(): void
{
    require_login();
    if ((current_user()['role'] ?? null) !== 'admin') {
        flash('error', 'Halaman ini hanya untuk admin.');
        redirect_to('/dashboard');
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function request_value(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function format_currency(float|int|null $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function format_date(?string $value, string $format = 'd-m-Y'): string
{
    if (!$value) {
        return '-';
    }
    return date($format, strtotime($value));
}

function calculate_distance_meters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371000;
    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);
    $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth * $c;
}

function upload_file(array $file, string $directory, array $allowedExtensions): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal.');
    }

    $maxSize = (int) app_config('app.upload_max_size_mb', 5) * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Ukuran file melebihi batas.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Tipe file tidak diizinkan.');
    }

    $targetDir = __DIR__ . '/../public/uploads/' . trim($directory, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $filename = uniqid($directory . '_', true) . '.' . $extension;
    $targetFile = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        throw new RuntimeException('Gagal menyimpan file upload.');
    }

    return 'public/uploads/' . trim($directory, '/') . '/' . $filename;
}
