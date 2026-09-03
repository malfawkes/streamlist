<?php include 'includes/header.php'; ?>

<section class='register-section'>
    <div class='wrap register-wrap'>
        <h1>Create New Account</h1>
        <!-- <p>Fill in the details to create your account</p> -->
        <form action="actions/process_register.php" method="post">
            <label for="username">Username:</label> <br>
            <input type="text" name="username" id="username" placeholder="Username" required>
            <br>
            <label for="email">Email:</label> <br>
            <input type="email" name="email" id="email" placeholder="Email" required>
            <br>
            <label for="password">Password:</label> <br>
            <input type="password" name="password" id="password" placeholder="Password" required>
            <br>
            <button name="create-account" type="submit">Create Account</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</section>

<?php include 'includes/footer.php'; ?>