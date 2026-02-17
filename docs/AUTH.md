# Authentication & Access Control

k5n Recipes supports three flexible authentication modes to accommodate different use cases, from a personal home network to a shared multi-user server.

## Authentication Modes

The mode is controlled by the `AUTH_MODE` variable in your `.env` file.

| Mode | `.env` Value | Description |
| :--- | :--- | :--- |
| **Open** | `open` | (Default) No restrictions. Anyone on the network can view, add, edit, or delete recipes. Ideal for trusted home LANs. |
| **PIN** | `pin` | **Family Mode.** Everyone can view recipes. A shared `ACCESS_PIN` is required to "unlock" destructive actions (Add, Edit, Delete, Import). |
| **User** | `user` | **Multi-user Mode.** Requires individual accounts. Supports recipe ownership and admin roles. |

---

## Configuration

### 1. Set the Mode
Update your `.env` file with your desired mode and PIN (if applicable):

```env
# Modes: open, pin, user
AUTH_MODE=pin

# Required for 'pin' mode
ACCESS_PIN=1234
```

### 2. Run Database Migrations
If you are moving to `user` mode or want to enable future ownership features, run the migration script to create the necessary tables:

```bash
php scripts/migrate_auth.php
```

---

## Usage Details

### PIN Mode (Family LAN)
- Users will see a 🔒 (Lock) icon in the navigation bar.
- To add or edit recipes, click the lock and enter the `ACCESS_PIN` defined in your `.env`.
- Once unlocked, the icon changes to 🔓. You can click it again to "Lock" the session.
- Unlocked state is stored in a secure PHP session.

### User Mode (Multi-user)
- Requires users to be present in the `rec_users` table.
- Passwords are encrypted using `password_hash()` with the Argon2id or Bcrypt algorithm.
- Current implementation allows all logged-in users to manage recipes. Future updates will introduce strict ownership (only the creator can edit).

---

## Developer Information

Permissions are checked globally via the `AuthManager` class.

### Checking Permissions in PHP
```php
$auth = getAuthManager();

if ($auth->can('edit')) {
    // Show edit form
}

if ($auth->can('delete')) {
    // Perform deletion
}
```

### Checking Permissions in JavaScript
The UI handles permission-based visibility automatically by hiding restricted elements from the DOM if the user is not authorized.
