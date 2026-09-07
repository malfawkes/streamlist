<?php

function validateRequired(string $value, string $label): ?string
{
    return trim($value) === '' ? "$label is required" : null;
}

function validateEmailFormat(string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "Enter a valid email address.";
}

function validatePasswordLength(string $value, int $min): ?string
{
    return strlen($value) < $min ? "Password must be at least $min characters." : null;
}

function validateRegisterInput(array $post): array
{
    $name     = trim($post['name'] ?? '');
    $email    = trim($post['email'] ?? '');
    $password = $post['password'] ?? '';      // NO trim — ever

    $errors = [];

    $errors[] = validateRequired($name, 'Username');
    $errors[] = validateRequired($email, 'Email');
    $errors[] = validateRequired($password, 'Password');

    if ($email !== '') {
        $errors[] = validateEmailFormat($email);
    }
    if (trim($password) !== '') {
        $errors[] = validatePasswordLength($password, 8);
    }

    $errors = array_values(array_filter($errors));

    if (empty($errors)) {
        $name  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    }

    return [
        'errors' => $errors,
        'data'   => ['name' => $name, 'email' => $email, 'password' => $password],
    ];
}

// ── Payment form validators (checkout simulation) ──────────────

function luhnValid(string $number): bool
{
    // The Luhn checksum — the actual algorithm cards use. Every card
    // number on Earth satisfies it; typos usually break it.
    $sum = 0;
    $len = strlen($number);
    for ($i = 0; $i < $len; $i++) {
        $digit = (int) $number[$len - 1 - $i];   // walk from the right
        if ($i % 2 === 1) {                       // every 2nd digit doubles
            $digit *= 2;
            if ($digit > 9) { $digit -= 9; }      // 10 → 1, 12 → 3 …
        }
        $sum += $digit;
    }
    return $sum % 10 === 0;                       // valid ⇔ divisible by 10
}

function validateCardNumber(string $value): ?string
{
    $number = preg_replace('/[\s\-]/', '', $value);   // strip spaces/dashes
    if (!preg_match('/^\d{15,19}$/', $number)) {
        return 'Enter a valid card number.';
    }
    return luhnValid($number) ? null : 'Card number failed validation.';
    // fun test values: 4242424242424242 (Stripe's test card — passes Luhn)
}

function validateCardExpiry(string $value): ?string
{
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $value)) {
        return 'Use MM/YY format.';               // month 01–12, two-digit year
    }
    [$mm, $yy] = explode('/', $value);
    if ('20' . $yy . '-' . $mm < date('Y-m')) {   // string compare works on Y-m
        return 'That card is expired.';
    }
    return null;
}

function validateCvv(string $value): ?string
{
    return preg_match('/^\d{3,4}$/', $value) ? null : 'CVV must be 3 or 4 digits.';
}