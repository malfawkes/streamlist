<?php include 'includes/header.php'; ?>

<?php
    $status = $_GET['status'] ?? null;
    $message = $_GET['message'] ?? null;
?>

<section class='login-section'>
    <div class='wrap login-wrap'>
        <h1>Welcome Back!</h1>
        
        <?php if ($status === 'registered'): ?>
            <p style="color: green;">Account created successfully! Please login.</p>
        <?php elseif ($status === 'error'): ?>
            <p style="color: red;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form action="actions/process_login.php" method="post">
            <label for="email">Email:</label> <br>
            <input type="email" name="email" id="email">
            <br>
            <label for="password">Password:</label> <br>
            <input type="password" name="password" id="password">
            <br>
            <button name="login" type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register</a></p>
    </div>

</section>

<?php include 'includes/footer.php'; ?>