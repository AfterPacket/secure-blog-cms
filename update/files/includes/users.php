<?php
/**
 * Secure Blog CMS - Users Class
 * Manages system users and their roles
 */

if (!defined('SECURE_CMS_INIT')) {
    die('Direct access not permitted');
}

class Users
{
    private static $instance = null;
    private $security;
    private static $validRoles = ['admin', 'editor', 'author'];

    /**
     * Singleton pattern
     * @return Users
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->security = Security::getInstance();
    }

    /**
     * Validates a password against the password policy.
     * @param string $password
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validatePasswordPolicy($password)
    {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return [
                'valid' => false,
                'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.',
            ];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one uppercase letter.',
            ];
        }
        if (!preg_match('/[a-z]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one lowercase letter.',
            ];
        }
        if (!preg_match('/[0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one digit.',
            ];
        }
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return [
                'valid' => false,
                'message' => 'Password must contain at least one special character.',
            ];
        }
        return ['valid' => true, 'message' => ''];
    }

    /**
     * Validates a username.
     * @param string $username
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateUsername($username)
    {
        if (strlen($username) < 3) {
            return [
                'valid' => false,
                'message' => 'Username must be at least 3 characters long.',
            ];
        }
        if (strlen($username) > 20) {
            return [
                'valid' => false,
                'message' => 'Username must be at most 20 characters long.',
            ];
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return [
                'valid' => false,
                'message' => 'Username may only contain letters, numbers, underscores, and dashes.',
            ];
        }
        return ['valid' => true, 'message' => ''];
    }

    /**
     * Returns the permissions for a given role.
     * @param string $role
     * @return array|null
     */
    public static function getRolePermissions($role)
    {
        $permissions = [
            'admin' => [
                'name' => 'Administrator',
                'description' => 'Full access — manage users, settings, resilience, comments, all posts',
                'can_manage_users' => true,
                'can_manage_settings' => true,
                'can_manage_resilience' => true,
                'can_moderate_comments' => true,
                'can_publish_any_post' => true,
                'can_edit_any_post' => true,
                'can_delete_any_post' => true,
                'can_create_posts' => true,
                'can_edit_own_posts' => true,
                'can_delete_own_posts' => true,
            ],
            'editor' => [
                'name' => 'Editor',
                'description' => 'Create, edit, and publish any post. Moderate comments.',
                'can_manage_users' => false,
                'can_manage_settings' => false,
                'can_manage_resilience' => false,
                'can_moderate_comments' => true,
                'can_publish_any_post' => true,
                'can_edit_any_post' => true,
                'can_delete_any_post' => true,
                'can_create_posts' => true,
                'can_edit_own_posts' => true,
                'can_delete_own_posts' => true,
            ],
            'author' => [
                'name' => 'Author',
                'description' => 'Create and edit own posts. Cannot publish or manage others.',
                'can_manage_users' => false,
                'can_manage_settings' => false,
                'can_manage_resilience' => false,
                'can_moderate_comments' => false,
                'can_publish_any_post' => false,
                'can_edit_any_post' => false,
                'can_delete_any_post' => false,
                'can_create_posts' => true,
                'can_edit_own_posts' => true,
                'can_delete_own_posts' => true,
            ],
        ];
        return $permissions[$role] ?? null;
    }

    /**
     * Gets the file path for a specific user.
     * @param string $username
     * @return string|false
     */
    private function getUserFile($username)
    {
        // Sanitize username to prevent directory traversal and invalid characters
        $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
        if (empty($username)) {
            return false;
        }
        return USERS_DIR . '/' . $username . '.json';
    }

    /**
     * Checks if a user exists.
     * @param string $username
     * @return bool
     */
    public function userExists($username)
    {
        $file = $this->getUserFile($username);
        return $file && file_exists($file);
    }

    /**
     * Gets a user's data.
     * @param string $username
     * @return array|null
     */
    public function getUser($username)
    {
        if (!$this->userExists($username)) {
            return null;
        }
        $file = $this->getUserFile($username);
        $content = file_get_contents($file);
        return json_decode($content, true);
    }

    /**
     * Gets all users.
     * @return array
     */
    public function getAllUsers()
    {
        $users = [];
        $files = glob(USERS_DIR . '/*.json');

        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $username = basename($file, '.json');
            $userData = $this->getUser($username);
            if ($userData) {
                // Do not expose password hash in general listings
                unset($userData['password_hash']);
                // Enrich with role display name and description
                $rolePerms = self::getRolePermissions($userData['role']);
                if ($rolePerms) {
                    $userData['role_name'] = $rolePerms['name'];
                    $userData['role_description'] = $rolePerms['description'];
                }
                $users[] = $userData;
            }
        }
        return $users;
    }

    /**
     * Adds a new user.
     * @param string $username
     * @param string $password
     * @param string $role
     * @return array
     */
    public function addUser($username, $password, $role)
    {
        // Validation
        if (empty($username) || empty($password) || empty($role)) {
            return ['success' => false, 'message' => 'Username, password, and role are required.'];
        }

        $usernameValidation = $this->validateUsername($username);
        if (!$usernameValidation['valid']) {
            return ['success' => false, 'message' => $usernameValidation['message']];
        }

        $passwordValidation = $this->validatePasswordPolicy($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['message']];
        }

        if ($this->userExists($username)) {
            return ['success' => false, 'message' => 'User already exists.'];
        }

        if (!in_array($role, self::$validRoles, true)) {
            return ['success' => false, 'message' => 'Invalid role. Allowed roles: admin, editor, author.'];
        }

        $userData = [
            'username' => $username,
            'password_hash' => defined('PASSWORD_ARGON2ID') 
                ? password_hash($password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
                : password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role' => $role,
            'created_at' => time(),
        ];

        $file = $this->getUserFile($username);
        if (!$file) {
             return ['success' => false, 'message' => 'Invalid username format.'];
        }

        if (file_put_contents($file, json_encode($userData, JSON_PRETTY_PRINT), LOCK_EX)) {
            chmod($file, 0600);
            $this->security->logSecurityEvent('User created', $username);
            return ['success' => true, 'message' => 'User created successfully.'];
        }

        return ['success' => false, 'message' => 'Failed to save user data.'];
    }

    /**
     * Updates a user's data.
     * @param string $username
     * @param array $data
     * @return array
     */
    public function updateUser($username, $data)
    {
        $user = $this->getUser($username);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        // Update password if a new one is provided
        if (!empty($data['password'])) {
            $passwordValidation = $this->validatePasswordPolicy($data['password']);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => $passwordValidation['message']];
            }
            $user['password_hash'] = defined('PASSWORD_ARGON2ID')
                ? password_hash($data['password'], PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
                : password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        // Update role if provided
        if (!empty($data['role'])) {
             if (!empty($data['role']) && !in_array($data['role'], self::$validRoles, true)) {
                return ['success' => false, 'message' => 'Invalid role. Allowed roles: admin, editor, author.'];
            }
            // Prevent admin from demoting themselves
            if (isset($data['current_username']) && $data['current_username'] === $username && $user['role'] === 'admin' && $data['role'] !== 'admin') {
                return ['success' => false, 'message' => 'You cannot change your own role to a non-admin role.'];
            }
            $user['role'] = $data['role'];
        }

        $file = $this->getUserFile($username);
        if (file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT), LOCK_EX)) {
            $this->security->logSecurityEvent('User updated', $username);
            return ['success' => true, 'message' => 'User updated successfully.'];
        }

        return ['success' => false, 'message' => 'Failed to update user data.'];
    }

    /**
     * Changes a user's password after verifying the current password.
     * @param string $username
     * @param string $current_password
     * @param string $new_password
     * @return array
     */
    public function changePassword($username, $current_password, $new_password)
    {
        $user = $this->getUser($username);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        $passwordValidation = $this->validatePasswordPolicy($new_password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['message']];
        }

        $user['password_hash'] = defined('PASSWORD_ARGON2ID')
            ? password_hash($new_password, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1])
            : password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);

        $file = $this->getUserFile($username);
        if (file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT), LOCK_EX)) {
            $this->security->logSecurityEvent('Password changed', $username);
            return ['success' => true, 'message' => 'Password changed successfully.'];
        }

        return ['success' => false, 'message' => 'Failed to update password.'];
    }

    /**
     * Deletes a user.
     * @param string $username
     * @return array
     */
    public function deleteUser($username)
    {
        // Prevent deleting the main admin from config, if defined
        if (defined('ADMIN_USERNAME') && $username === ADMIN_USERNAME) {
            return ['success' => false, 'message' => 'Cannot delete the primary administrator.'];
        }

        if (!$this->userExists($username)) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $file = $this->getUserFile($username);
        if (unlink($file)) {
            $this->security->logSecurityEvent('User deleted', $username);
            return ['success' => true, 'message' => 'User deleted successfully.'];
        }

        return ['success' => false, 'message' => 'Failed to delete user.'];
    }
}