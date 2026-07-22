<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class StrongPassword implements ValidationRule
{
    public function __construct(private readonly int $minLength = 8) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le mot de passe est invalide.');

            return;
        }

        $configuredMin = (int) (DB::table('remote_configs')
            ->where('key_name', 'min_password_length')
            ->where('is_active', 1)
            ->value('value_text') ?: $this->minLength);

        $min = max(8, $configuredMin);

        if (strlen($value) < $min) {
            $fail("Le mot de passe doit contenir au moins {$min} caractères.");

            return;
        }

        if (! preg_match('/[a-z]/', $value)) {
            $fail('Le mot de passe doit contenir au moins une lettre minuscule.');

            return;
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Le mot de passe doit contenir au moins une lettre majuscule.');

            return;
        }

        if (! preg_match('/[0-9]/', $value)) {
            $fail('Le mot de passe doit contenir au moins un chiffre.');

            return;
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Le mot de passe doit contenir au moins un caractère spécial.');
        }
    }
}
