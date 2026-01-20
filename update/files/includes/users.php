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
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'];
        }
        if ($this->userExists($username)) {
            return ['success' => false, 'message' => 'User already exists.'];
        }

        // TODO: Add role validation logic to check against a list of valid roles

        $userData = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
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
            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                 return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.'];
            }
            $user['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Update role if provided
        if (!empty($data['role'])) {
             // TODO: Add role validation logic
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
