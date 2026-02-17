<?php

declare(strict_types=1);

namespace Recipes\Tests;

use PHPUnit\Framework\TestCase;
use Recipes\Auth\AuthManager;
use Recipes\Database\Database;

final class AuthManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure a clean session for each test
        $_SESSION = [];
        // Clear environment variables that might interfere
        unset($_ENV['AUTH_MODE']);
        unset($_ENV['ACCESS_PIN']);
        // Clear mock database results
        Database::clearMockQueryResults();
    }

    public function testOpenModeAllowsAllActions(): void
    {
        // Set mode to open
        $_ENV['AUTH_MODE'] = 'open';

        $auth = new AuthManager();
        $this->assertEquals('open', $auth->getMode());

        $this->assertTrue($auth->can('view'));
        $this->assertTrue($auth->can('edit'));
        $this->assertTrue($auth->can('delete'));
        $this->assertTrue($auth->can('admin')); // Even admin actions are "allowed" in open mode
    }

    public function testPinModeRestrictsActionsUntilUnlocked(): void
    {
        $_ENV['AUTH_MODE'] = 'pin';
        $_ENV['ACCESS_PIN'] = '1234';

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
        $_ENV['AUTH_MODE'] = 'user';
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
        $_ENV['AUTH_MODE'] = 'user';
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

    public function testUserModeOwnershipCheckForOwner(): void
    {
        $_ENV['AUTH_MODE'] = 'user';
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
        $_ENV['AUTH_MODE'] = 'user';
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
        $_ENV['AUTH_MODE'] = 'user';
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
        $mockResult = (object)[]; // PDOStatement object
        $mockResult->fetchNumCalls = 0;
        $mockResult->returnValues = [
            [$ownerId] // Simulate row with user_id
        ];

        Database::setMockQueryResult($recipeId, $mockResult);
    }
}
