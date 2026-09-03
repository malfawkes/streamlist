<?php
require_once 'includes/auth.php';
requireGuest();

$status = $_GET['status']  ?? null;
$message = $_GET['message'] ?? null;

include 'includes/header.php'; 
?>

<section class='register-section'>
    <div class='wrap register-wrap'>
        <h1>Create New Account</h1>

        <?php if ($status === 'error'): ?>
            <p style="color: red;"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <!-- <p>Fill in the details to create your account</p> -->
        <form action="actions/process_register.php" method="post">
            <label for="name">Username:</label> <br>
            <input type="text" name="name" id="name" placeholder="Username" required>
            <br>
            <label for="email">Email:</label> <br>
            <input type="email" name="email" id="email" placeholder="Email" required>
            <br>
            <label for="password">Password:</label> <br>
            <input minlength="8" type="password" name="password" id="password" placeholder="Password" required>
            <br>
            <button name="create-account" type="submit">Create Account</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</section>

<?php include 'includes/footer.php'; ?>