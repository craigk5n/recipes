<?php

declare(strict_types=1);

namespace Recipes\Tests;

use PHPUnit\Framework\TestCase;
use Recipes\Auth\AuthManager;
use Recipes\Config;
use Recipes\Database\Database;

final class AuthManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetAuthConfig();
        // Clear mock database results
        Database::clearMockQueryResults();
    }

    protected function tearDown(): void
    {
        // Don't leak this class's AUTH_MODE into other test classes.
        $this->resetAuthConfig();
    }

    /**
     * Return auth config to a pristine state.
     *
     * Config caches settings for the whole process, so without the reset every
     * test after the first would silently reuse the first test's AUTH_MODE.
     * The env vars are cleared through putenv() as well as $_ENV because
     * Config::load() reads getenv() first, and loadEnvFile() overwrites $_ENV
     * from any .env present — so $_ENV alone is not reliably in charge.
     */
    private function resetAuthConfig(): void
    {
        // Ensure a clean session for each test
        $_SESSION = [];
        putenv('AUTH_MODE');
        putenv('ACCESS_PIN');
        unset($_ENV['AUTH_MODE'], $_ENV['ACCESS_PIN']);
        Config::reset();
    }

    public function testOpenModeAllowsAllActions(): void
    {
        // Set mode to open
        putenv('AUTH_MODE=open');

        $auth = new AuthManager();
        $this->assertEquals('open', $auth->getMode());

        $this->assertTrue($auth->can('view'));
        $this->assertTrue($auth->can('edit'));
        $this->assertTrue($auth->can('delete'));
        $this->assertTrue($auth->can('admin')); // Even admin actions are "allowed" in open mode
    }

    public function testPinModeRestrictsActionsUntilUnlocked(): void
    {
        putenv('AUTH_MODE=pin');
        putenv('ACCESS_PIN=1234');

        $auth = new AuthManager();
        $this->assertEquals('pin', $auth->getMode());

        // By default, view is allowed, but edit/delete are not
        $this->assertTrue($auth->can('view'));
        $this->assertFalse($auth->can('edit'));
        $this->assertFalse($auth->can('delete'));
        $this->assertFalse($auth->can('admin'));

        // Unlock with wrong PIN should fail
        $this->assertFalse($auth->unlockWithPin('wrong-pin'));
        $this->assertFalse($auth->can('edit'));

        // Unlock with correct PIN should succeed
        $this->assertTrue($auth->unlockWithPin('1234'));
        $this->assertTrue($auth->can('edit'));
        $this->assertTrue($auth->can('delete'));
        $this->assertTrue($auth->can('admin')); // Pin unlocks everything for the session
    }

    public function testUserModeForRegularUser(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        // No one is logged in, all restricted actions should fail
        $this->assertFalse($auth->can('edit'));
        $this->assertFalse($auth->can('delete'));
        $this->assertFalse($auth->can('admin'));

        // Simulate a regular user login
        $_SESSION['user_id'] = 123;
        $_SESSION['username'] = 'testuser';
        $_SESSION['is_admin'] = 0;

        // Regular user should be able to perform edit/delete (non-resource specific)
        $this->assertTrue($auth->can('edit'));
        $this->assertTrue($auth->can('delete'));
        $this->assertFalse($auth->can('admin')); // Admin actions should still fail
    }

    public function testUserModeForAdminUser(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        // Simulate an admin user login
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['is_admin'] = 1;

        // Admin should have access to all actions
        $this->assertTrue($auth->can('view'));
        $this->assertTrue($auth->can('edit'));
        $this->assertTrue($auth->can('delete'));
        $this->assertTrue($auth->can('admin'));
    }

    public function testLogoutClearsTheSession(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        $_SESSION['user_id'] = 123;
        $_SESSION['username'] = 'testuser';
        $_SESSION['is_admin'] = 0;

        $auth->logout();

        $this->assertSame([], $_SESSION);
        $this->assertNull($auth->getCurrentUser());
        $this->assertFalse($auth->can('edit'));
    }

    public function testUserModeOwnershipCheckForOwner(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        // Mock a recipe owned by user 123
        $this->mockRecipeOwner(101, 123);

        // Simulate user 123 login
        $_SESSION['user_id'] = 123;
        $_SESSION['username'] = 'testuser';
        $_SESSION['is_admin'] = 0;

        // Owner should be able to edit and delete their own recipe
        $this->assertTrue($auth->can('edit', 101));
        $this->assertTrue($auth->can('delete', 101));
    }

    public function testUserModeOwnershipCheckForNonOwner(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        // Mock a recipe owned by user 456
        $this->mockRecipeOwner(102, 456);

        // Simulate user 123 login
        $_SESSION['user_id'] = 123;
        $_SESSION['username'] = 'testuser';
        $_SESSION['is_admin'] = 0;

        // Non-owner should NOT be able to edit or delete the recipe
        $this->assertFalse($auth->can('edit', 102));
        $this->assertFalse($auth->can('delete', 102));
    }

    public function testUserModeOwnershipCheckForAdmin(): void
    {
        putenv('AUTH_MODE=user');
        $auth = new AuthManager();

        // Mock a recipe owned by user 123
        $this->mockRecipeOwner(103, 123);

        // Simulate admin login
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['is_admin'] = 1;

        // Admin should be able to edit and delete any recipe
        $this->assertTrue($auth->can('edit', 103));
        $this->assertTrue($auth->can('delete', 103));
    }

    private function mockRecipeOwner(int $recipeId, int $ownerId): void
    {
        // For testing purposes, we need to mock the Database::query call
        // This is a simplified mock; a full solution would use a mocking framework like Mockery/Prophecy
        $mockResult = (object)[];
        $mockResult->returnValues = [
            [$ownerId] // Simulate row with user_id
        ];

        Database::setMockQueryResult($recipeId, $mockResult);
    }
}
