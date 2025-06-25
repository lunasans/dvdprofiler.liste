<?php
declare(strict_types=1);

// Bootstrap lädt $pdo, BASE_URL usw.
require_once __DIR__ . '/../includes/bootstrap.php';

session_start();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];

        // Nutze BASE_URL, falls gesetzt, ansonsten relativen Pfad
        $redirect = (defined('BASE_URL') && BASE_URL !== '')
            ? BASE_URL . '/admin/index.php?pages=dashbord'
            : 'index.php?pages=dashbord';

        header("Location: $redirect");
        exit;
    } else {
        $error = "Login fehlgeschlagen.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
</head>
<body>
    <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    <section class="container">
        <div class="login-container">
            <div class="circle circle-one"></div>
            <div class="form-container">
                <h1 class="opacity">Login</h1>
                <form method="post" action="">
                    <input type="email" name="email" placeholder="eMail" required />
                    <input type="password" name="password" placeholder="passwort" required />
                    <button class="opacity" type="submit">Absenden</button>
                </form>
                <div class="register-forget opacity">
                    <!-- <a href="">REGISTER</a> -->
                    <!-- <a href="">FORGOT PASSWORD</a>-->
                </div>
            </div>
            <div class="circle circle-two"></div>
        </div>
        <div class="theme-btn-container"></div>
    </section>
<script src="js/login.js"></script>
</body>
</html>