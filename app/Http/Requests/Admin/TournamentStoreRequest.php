<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;

class TournamentStoreRequest extends TournamentUpdateRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Title and modes are required for creation
        $rules['title'] = 'required|string|max:256';
        $rules['modes'] = 'required|array';

        return $rules;
    }
}
