<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Centralized server-side validation. Client-side validation is a UX aid
 * only; every write path runs through this class.
 *
 * Usage:
 *   $v = Validator::make($input, [
 *       'student_number' => 'required|max:30|alnumdash',
 *       'email'          => 'nullable|email|max:150',
 *       'gender'         => 'required|in:male,female',
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    private function __construct(
        private readonly array $input,
        private readonly array $rules,
    ) {
        $this->run();
    }

    public static function make(array $input, array $rules): self
    {
        return new self($input, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        return reset($this->errors) ?: 'Validation failed.';
    }

    /** Validated subset of the input (only fields with rules). */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->input)) {
                $value = $this->input[$field];
                $out[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        return $out;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->input[$field] ?? null;
            $isEmpty = $value === null || $value === '';

            if (in_array('nullable', $rules, true) && $isEmpty) {
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $label = ucwords(str_replace('_', ' ', $field));

                switch ($name) {
                    case 'required':
                        if ($isEmpty) {
                            $this->errors[$field] = "$label is required.";
                        }
                        break;
                    case 'max':
                        if (!$isEmpty && mb_strlen((string) $value) > (int) $arg) {
                            $this->errors[$field] = "$label must not exceed $arg characters.";
                        }
                        break;
                    case 'min':
                        if (!$isEmpty && mb_strlen((string) $value) < (int) $arg) {
                            $this->errors[$field] = "$label must be at least $arg characters.";
                        }
                        break;
                    case 'email':
                        if (!$isEmpty && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field] = "$label must be a valid email address.";
                        }
                        break;
                    case 'int':
                        if (!$isEmpty && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->errors[$field] = "$label must be an integer.";
                        }
                        break;
                    case 'numeric':
                        if (!$isEmpty && !is_numeric($value)) {
                            $this->errors[$field] = "$label must be numeric.";
                        }
                        break;
                    case 'date':
                        if (!$isEmpty && strtotime((string) $value) === false) {
                            $this->errors[$field] = "$label must be a valid date.";
                        }
                        break;
                    case 'time':
                        if (!$isEmpty && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value)) {
                            $this->errors[$field] = "$label must be a valid time (HH:MM).";
                        }
                        break;
                    case 'in':
                        if (!$isEmpty && !in_array((string) $value, explode(',', (string) $arg), true)) {
                            $this->errors[$field] = "$label has an invalid value.";
                        }
                        break;
                    case 'alnumdash':
                        if (!$isEmpty && !preg_match('/^[a-zA-Z0-9\-_]+$/', (string) $value)) {
                            $this->errors[$field] = "$label may only contain letters, numbers, dashes and underscores.";
                        }
                        break;
                    case 'name':
                        if (!$isEmpty && !preg_match("/^[\p{L}\s.,'\-]+$/u", (string) $value)) {
                            $this->errors[$field] = "$label contains invalid characters.";
                        }
                        break;
                    case 'phone':
                        if (!$isEmpty && !preg_match('/^[0-9+\-\s()]{7,20}$/', (string) $value)) {
                            $this->errors[$field] = "$label must be a valid phone number.";
                        }
                        break;
                    case 'hex':
                        if (!$isEmpty && !preg_match('/^[A-Fa-f0-9]+$/', (string) $value)) {
                            $this->errors[$field] = "$label must be hexadecimal.";
                        }
                        break;
                    case 'mac':
                        if (!$isEmpty && !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/', (string) $value)) {
                            $this->errors[$field] = "$label must be a valid MAC address.";
                        }
                        break;
                    case 'password':
                        $min = (int) \App\Core\Config::get('security.password_min_length', 12);
                        $val = (string) $value;
                        if (!$isEmpty && (
                            mb_strlen($val) < $min
                            || !preg_match('/[A-Z]/', $val)
                            || !preg_match('/[a-z]/', $val)
                            || !preg_match('/\d/', $val)
                        )) {
                            $this->errors[$field] = "$label must be at least $min characters and contain uppercase, lowercase and numbers.";
                        }
                        break;
                }

                if (isset($this->errors[$field])) {
                    break; // first error per field is enough
                }
            }
        }
    }
}
