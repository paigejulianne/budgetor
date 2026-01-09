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
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $result = registerUser($email, $password);

        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Budgetor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <h1>Budgetor</h1>
        <h2>Create Account</h2>

        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       minlength="8" placeholder="Minimum 8 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary">Register</button>
        </form>

        <p class="auth-link">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>
</body>
</html>
