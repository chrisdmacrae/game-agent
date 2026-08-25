<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'handle' => $this->handleRules($userId),
            'discord_username' => ['nullable', 'string', 'max:64'],
            'bio' => ['nullable', 'string', 'max:180'],
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * The public identity on build pages: slug-ish, unique, and short enough
     * to sit in a mono byline.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function handleRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'max:30',
            'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/i',
            $userId === null
                ? Rule::unique(User::class, 'handle')
                : Rule::unique(User::class, 'handle')->ignore($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate user emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
