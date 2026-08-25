<?php

namespace App\Http\Requests;

use App\Domain\Poe2\Validation\BuildRules;
use App\Models\Build;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The web editor's save (scope §3.8). The payload rules are the same ones the
 * save_build MCP tool validates against — BuildRules is the single definition,
 * so the editor can never accept a shape the tool rejects.
 *
 * Ownership is enforced in the controller, which can tell a missing build
 * (404) from someone else's (403).
 */
class BuildUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => 'required|string|max:120',
            'summary' => 'nullable|string|max:500',
            'guide_markdown' => 'nullable|string|max:30000',
            'visibility' => 'required|string|in:'.Build::VISIBILITY_DRAFT.','.Build::VISIBILITY_PUBLIC,
        ], BuildRules::rules('build.'));
    }
}
