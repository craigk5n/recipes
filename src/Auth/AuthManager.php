<?php

declare(strict_types=1);

namespace Recipes\Auth;

use Recipes\Database\Database;
use Recipes\Config;
use Recipes\I18n\Translator;
use Recipes\Security\Security;
use function Recipes\I18n\t;

class AuthManager
{
    private string $mode;
    private ?string $pin;

    /** bcrypt hash of a random string, verified when the username doesn't exist. */
    private const DUMMY_HASH = '$2y$10$rSzwPDj14B5a3ou8FQSKWOwyU024U4nXiwugGtn7rovTM2ufus0Fm';

    public function __construct()
    {
        $this->mode = Config::get('AUTH_MODE');
        $this->pin = Config::get('ACCESS_PIN');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Check if the current user has permission for an action.
     * 
     * @param string $action 'view', 'edit', 'delete', 'admin'
     * @param int|null $recipeId Optional ID of the recipe to check ownership for
     * @return bool
     */
    public function can(string $action, ?int $recipeId = null): bool
    {
        if ($this->mode === 'open') {
            return true;
        }

        if ($action === 'view') {
            return true;
        }

        // Admin can do everything
        if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
            return true;
        }

        if ($this->mode === 'pin') {
            return $_SESSION['auth_unlocked'] ?? false;
        }

        if ($this->mode === 'user') {
            if (!isset($_SESSION['user_id'])) {
                return false;
            }
            
            if ($action === 'admin') {
                return false; // Already checked admin above
            }
            
            // If we have a recipeId, check ownership
            if ($recipeId !== null) {
                return $this->isOwner($recipeId, (int)$_SESSION['user_id']);
            }
            
            return true; // Any logged in user can perform non-resource specific actions
        }

        return false;
    }

    /**
     * Check if a user owns a specific recipe.
     */
    private function isOwner(int $recipeId, int $userId): bool
    {
        $res = Database::query(
            "SELECT user_id FROM rec_recipe WHERE rec_id = ?",
            [$recipeId]
        );
        if ($res && $row = Database::fetchRow($res)) {
            // New recipes might have NULL user_id, consider them owned by nobody/unrestricted?
            // For safety, only allow if it matches.
            return $row[0] !== null && (int)$row[0] === $userId;
        }
        return false;
    }

    public function unlockWithPin(string $pin): bool
    {
        if ($this->mode !== 'pin') {
            return false;
        }

        if ($this->pin !== null && $this->pin !== '' && hash_equals($this->pin, $pin)) {
            // New session id on privilege change, so a fixated id is useless.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['auth_unlocked'] = true;
            return true;
        }

        return false;
    }

    public function login(string $username, string $password): bool
    {
        if ($this->mode !== 'user') {
            return false;
        }

        $res = Database::query(
            "SELECT user_id, password_hash, is_admin FROM rec_users WHERE username = ?",
            [$username]
        );
        
        $row = $res ? Database::fetchRow($res) : false;
        // Always run password_verify so unknown usernames take as long as
        // known ones and can't be enumerated by timing.
        $hash = $row ? $row[1] : self::DUMMY_HASH;
        if (password_verify($password, $hash) && $row) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row[0];
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = $row[2];
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        Security::destroySession();
    }

    public function getCurrentUser(): ?array
    {
        if ($this->mode === 'user' && isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'is_admin' => $_SESSION['is_admin']
            ];
        }
        return null;
    }
}

/**
 * Global helper functions for Auth
 */

// Holds the AuthManager singleton, or null until getAuthManager() builds it.
// Not a @var docblock: the tag is only honoured above a plain variable
// assignment, so on a $GLOBALS entry it does nothing but trip static analysis.
$GLOBALS['_auth_manager_instance'] = null;

function getAuthManager(): AuthManager
{
    if ($GLOBALS['_auth_manager_instance'] === null) {
        $GLOBALS['_auth_manager_instance'] = new AuthManager();
    }
    return $GLOBALS['_auth_manager_instance'];
}
