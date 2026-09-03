<?php

function validateRequire(string $value, string $label): ?string
{
    return trim($value) === '' ? "$label is required" : null;
}

function valideEmailFormat(string $email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? null : "Invalid email address";
}

function validatePasswordLength(string $value, int $min = 8): ?string
{
    return strlen($value) < $min ? "Password must be at least $min characters" : null;
}
