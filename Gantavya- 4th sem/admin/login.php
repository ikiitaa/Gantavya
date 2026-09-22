<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
$existing = current_user();
if ($existing && $existing['role'] === 'admin') {
    redirect('admin/index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Gantavya</title>
    <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body class="page-bg">
<main class="result-wrap">
    <section class="result-card" style="max-width:460px; text-align:left;">
        <p class="eyebrow">Protected area</p>
        <h1>Administrator login</h1>
        <p>Use the administrator account created through the one-time setup page.</p>
        <form id="admin-login-form">
            <label>Email address<input type="email" name="email" autocomplete="email" required></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="btn btn-primary full" type="submit">Login</button>
            <p class="form-message"></p>
        </form>
        <div class="button-row center" style="margin-top:16px;"><a href="../index.php">Back to website</a></div>
    </section>
</main>
<script>
document.getElementById('admin-login-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const message = form.querySelector('.form-message');
    const button = form.querySelector('button');
    button.disabled = true;
    const payload = new FormData(form);
    payload.append('csrf_token', <?= json_encode(csrf_token()) ?>);
    try {
        const response = await fetch('../api/login.php', { method: 'POST', body: payload });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Login failed.');
        window.location.href = data.redirect;
    } catch (error) {
        message.className = 'form-message error';
        message.textContent = error.message;
        button.disabled = false;
    }
});
</script>
</body>
</html>
