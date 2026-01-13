<div class="login-wrapper">
    <header>
        <img src="images/4884-logo.png" alt="Ihr Leipzig Taxi 4884" class="login-logo">
    </header>
    <main class="login-card">
        <h1>Login</h1>
        <?php if (!empty($error)): ?>
            <p class="login-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form action="/login" method="POST" class="login-form">
            <label for="username">Benutzername <small>(Email oder Fahrernummer)</small>:</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Login</button>
        </form>
    </main>
</div>
