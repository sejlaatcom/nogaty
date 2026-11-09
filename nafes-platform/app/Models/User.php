<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** حسابات المستخدمين وأدوارهم */
final class User
{
    public const USERNAME_RULE = '/^[a-z0-9_.\-]{3,64}$/';

    public static function find(int $id): ?array
    {
        return DB::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return DB::first('SELECT * FROM users WHERE username = ?', [mb_strtolower($username)]);
    }

    public static function usernameTaken(string $username, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE username = ? AND id <> ?', [mb_strtolower($username), $exceptId]);
    }

    /** إنشاء مستخدم: password نصي يُشفّر هنا */
    public static function create(array $data, array $roles = []): int
    {
        $id = DB::insert('users', [
            'username'             => mb_strtolower(trim($data['username'])),
            'password_hash'        => password_hash((string) $data['password'], PASSWORD_DEFAULT),
            'full_name'            => trim($data['full_name']),
            'email'                => ($data['email'] ?? '') ?: null,
            'is_active'            => (int) ($data['is_active'] ?? 1),
            'must_change_password' => (int) ($data['must_change_password'] ?? 1),
        ]);
        self::setRoles($id, $roles);
        return $id;
    }

    public static function setRoles(int $userId, array $slugs): void
    {
        DB::delete('user_roles', 'user_id = ?', [$userId]);
        foreach (array_unique($slugs) as $slug) {
            DB::run('INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?', [$userId, $slug]);
        }
    }

    public static function addRole(int $userId, string $slug): void
    {
        DB::run('INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?', [$userId, $slug]);
    }

    public static function roleSlugs(int $userId): array
    {
        return DB::column('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?', [$userId]);
    }

    public static function setPassword(int $userId, string $password, bool $mustChange): void
    {
        DB::update('users', [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => $mustChange ? 1 : 0,
        ], 'id = :id', ['id' => $userId]);
    }

    /** سياسة كلمة المرور: 8 أحرف على الأقل وتتضمن حرفاً ورقماً */
    public static function passwordErrors(string $password): array
    {
        $errors = [];
        if (mb_strlen($password) < 8) {
            $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';
        }
        if (!preg_match('/\d/', $password) || !preg_match('/[^\d\s]/u', $password)) {
            $errors[] = 'كلمة المرور يجب أن تحتوي على أحرف وأرقام.';
        }
        return $errors;
    }
}
