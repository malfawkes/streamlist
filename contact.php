<?php
// contact.php — public contact form.
// Deliberately NOT login-gated: support must be reachable pre-signup.

require_once 'includes/auth.php';   // session boot + csrfToken()
// (no requireLogin — by design)

require_once 'database/db.php';

 $status  = $_GET['status']  ?? null;
 $message = $_GET['message'] ?? null;

include 'includes/header.php';
?>

    <main>
        <div class="wrap">

            <div class="contact-wrap">
                <h1>Contact Us</h1>
                <p>Questions, feedback, movie suggestions. We read everything.</p>
                <br>

                <?php if ($status === 'sent'): ?>
                    <p class="message message-success">Message sent. We'll get back to you soon!</p>
                <?php elseif ($status === 'error'): ?>
                    <p class="message message-error"><?= htmlspecialchars($message) ?></p>
                <?php endif; ?>

                <form method="post" action="actions/process_contact.php" class="contact-form">
                    <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off">

                    <label for="name">Name:</label><br>
                    <input type="text" name="name" id="name" required><br>

                    <label for="email">Email:</label><br>
                    <input type="email" name="email" id="email" required><br>

                    <label for="subject">Subject <small>(optional)</small>:</label><br>
                    <input type="text" name="subject" id="subject"><br>

                    <label for="message">Message:</label><br>
                    <textarea name="message" id="message" rows="6" required></textarea><br>

                    <button name="send-message" type="submit">Send Message</button>
                </form>
            </div>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>