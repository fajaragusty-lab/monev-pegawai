<?php
class Auth
{
    public function __construct(private Database $db)
    {
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->db->one('SELECT * FROM employees WHERE username = :username AND status = :status LIMIT 1', [
            'username' => $username,
            'status' => 'active',
        ]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role'],
            'region_id' => $user['region_id'] ? (int) $user['region_id'] : null,
        ];
        return true;
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
    }
}
