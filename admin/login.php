<?php
session_start();

$error = '';
$success = '';

// Hardcoded gebruikers (in productie: database gebruiken!)
$users = [
    'ivar.pijper@gmail.com' => password_hash('jWagenda2026!', PASSWORD_BCRYPT),
    'joel.plas@gmail.com' => password_hash('jWagenda2026!', PASSWORD_BCRYPT)
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email en wachtwoord zijn verplicht.';
    } elseif (!isset($users[$email])) {
        $error = 'Email of wachtwoord is onjuist.';
    } elseif (!password_verify($password, $users[$email])) {
        $error = 'Email of wachtwoord is onjuist.';
    } else {
        // Inloggen succesvol
        $_SESSION['user_email'] = $email;
        $_SESSION['logged_in'] = true;
        header('Location: /admin/dashboard.php');
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    $success = 'Je bent uitgelogd.';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>jeWelste Admin — Inloggen</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(135deg, #161616 0%, #313131 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 16px 48px rgba(0,0,0,.3);
            max-width: 400px;
            width: 100%;
            padding: 48px 32px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .login-header h1 {
            font-size: 24px;
            color: #161616;
            margin-bottom: 8px;
        }
        .login-header p {
            color: #777;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #161616;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color .2s;
        }
        input:focus {
            outline: none;
            border-color: #FFE800;
            box-shadow: 0 0 0 2px rgba(255,232,0,.1);
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #161616;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background .2s;
        }
        .btn:hover {
            background: #313131;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>jeWelste Admin</h1>
            <p>Agenda management</p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="je@email.com" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn">Inloggen</button>
        </form>

        <div class="login-footer">
            <p>jeWelste Agenda Manager</p>
        </div>
    </div>
</body>
</html>
