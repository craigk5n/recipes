<?php
include "rec_includes.php";

use function Recipes\Auth\getAuthManager;

$auth = getAuthManager();
$mode = $auth->getMode();

if ($auth->can('edit') && $mode !== 'user') {
    header("Location: index.php");
    exit;
}

$error = $_GET['error'] ?? '';
$pagetitle = $mode === 'pin' ? 'Unlock' : 'Login';
?>
<html>
<head>
<title><?php echo $pagetitle; ?> - <?php echo $title; ?></title>
<?php include "includes/styles.php"; ?>
</head>
<body class="bg-light">

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><?php echo $pagetitle; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($error === 'invalid') { ?>
                        <div class="alert alert-danger">Invalid username or password.</div>
                    <?php } elseif ($error === 'invalid_pin') { ?>
                        <div class="alert alert-danger">Invalid PIN.</div>
                    <?php } ?>

                    <?php if ($mode === 'pin') { ?>
                        <form action="auth_handler.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="unlock">
                            <div class="mb-3">
                                <label for="pin" class="form-label">Access PIN</label>
                                <input type="password" class="form-control form-control-lg text-center" id="pin" name="pin" autofocus required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Unlock</button>
                            </div>
                        </form>
                    <?php } else { ?>
                        <form action="auth_handler.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" autofocus required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                    <?php } ?>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none">&larr; Back to Recipes</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
