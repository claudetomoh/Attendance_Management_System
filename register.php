<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$errors = [];
$oldName = '';
$oldEmail = '';
$oldRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = sanitize($_POST['name'] ?? '');
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $passwordInput = $_POST['password'] ?? '';
    $roleInput = sanitize($_POST['role'] ?? '');

    if ($nameInput === '' || mb_strlen($nameInput) < 3) {
        add_flash($errors, 'Please enter a full name that is at least 3 characters long.');
    }

    if (!$emailInput) {
        add_flash($errors, 'Please enter a valid email address.');
    }

    if (strlen($passwordInput) < 6) {
        add_flash($errors, 'Password must have at least 6 characters.');
    }

    if (!in_array($roleInput, ['faculty', 'student', 'intern'], true)) {
        add_flash($errors, 'Choose a valid role for your account.');
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute(['email' => $emailInput]);

        if ($check->fetch()) {
            add_flash($errors, 'This email is already registered. Please log in instead.');
        } else {
            $hash = password_hash($passwordInput, PASSWORD_DEFAULT);
            $insert = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :hash, :role)'
            );
            $insert->execute([
                'name' => $nameInput,
                'email' => $emailInput,
                'hash' => $hash,
                'role' => $roleInput,
            ]);

            header('Location: login.php?registered=1');
            exit;
        }
    }

    $oldName = escape($nameInput);
    $oldEmail = escape($_POST['email'] ?? '');
    $oldRole = escape($roleInput);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SmartRegister – Create Account</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body class="register-page">
  <div class="register-container" role="main">
    <h1>Create your SmartRegister account</h1>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error" aria-live="polite">
        <?php foreach ($errors as $error): ?>
          <p><?php echo escape($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="" novalidate aria-label="Registration form">
      <label for="name">Full Name</label>
      <input
        type="text"
        id="name"
        name="name"
        placeholder="Claude Tomoh"
        value="<?php echo $oldName; ?>"
        required
        minlength="3"
      />

      <label for="email">Email</label>
      <input
        type="email"
        id="email"
        name="email"
        placeholder="you@ashesi.edu.gh"
        value="<?php echo $oldEmail; ?>"
        required
      />

      <label for="password">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        placeholder="Create a password"
        required
        minlength="6"
      />

      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="" disabled <?php echo $oldRole === '' ? 'selected' : ''; ?>>-- Select your role --</option>
        <option value="faculty" <?php echo $oldRole === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
        <option value="student" <?php echo $oldRole === 'student' ? 'selected' : ''; ?>>Student</option>
        <option value="intern" <?php echo $oldRole === 'intern' ? 'selected' : ''; ?>>Intern</option>
      </select>

      <button type="submit">Sign Up</button>
    </form>

    <div class="extra-links">
      <span>Already have an account?</span>
      <a href="login.php">Log in</a>
    </div>
  </div>
</body>
</html>
