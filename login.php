<?php
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = loginUser($email, $password);

    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Budgetor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <h1>Budgetor</h1>
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Login</button>
        </form>

        <p class="auth-link">
            Don't have an account? <a href="register.php">Register</a>
        </p>
    </div>
</body>
</html>
