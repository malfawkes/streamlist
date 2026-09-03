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
    $name = trim($post['name'] ?? '');
    $email = trim($post['email'] ?? '');
    $password = trim($post['password'] ?? '');

    $errors = array_values(array_filter([
        validateRequired($name, 'Username'),
        validateRequired($email, 'Email'),
        validateEmailFormat($email),
        validateRequired($password, 'Password'),
        validatePasswordLength($password, 8),
    ]));

    if(empty($errors)) {
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    }

    return [
        'errors' => $errors,   
        'data'   => ['name' => $name, 'email' => $email, 'password' => $password],
    ];
}