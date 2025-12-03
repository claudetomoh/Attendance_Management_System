<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$errors = [];
$emailValue = '';
$successMessage = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
  $successMessage = 'Account created. Please log in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $passwordInput = $_POST['password'] ?? '';

    if (!$emailInput) {
        add_flash($errors, 'Please provide a valid email address.');
    }

    if ($passwordInput === '' || strlen($passwordInput) < 6) {
        add_flash($errors, 'Password must be at least 6 characters.');
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id, name, role, password_hash FROM ' . TABLE_USERS . ' WHERE email = :email');
        $stmt->execute(['email' => $emailInput]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($passwordInput, $user['password_hash'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'role' => $user['role'],
            ];

            header('Location: dashboard.php');
            exit;
        }

        add_flash($errors, 'We could not find an account with those credentials.');
    }

    $emailValue = escape($_POST['email'] ?? '');
  } else {
    $emailValue = '';
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Log In</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="login-page">
  <div class="login-container" role="main">
    <h1>Log in to SmartRegister</h1>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" aria-live="polite">
          <?php foreach ($errors as $error): ?>
            <p><?php echo escape($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($successMessage): ?>
        <div class="alert alert-success" aria-live="polite">
          <p><?php echo escape($successMessage); ?></p>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate aria-label="Login form">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@school.edu" value="<?php echo $emailValue; ?>" required aria-required="true" />

        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••" required aria-required="true" minlength="6" />

        <button type="submit">Log In</button>
      </form>

    <div class="extra-links">
      <span>Don’t have an account?</span>
      <a href="register.php">Create one</a>
    </div>
  </div>
</body>
</html>
