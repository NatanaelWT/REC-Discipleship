<?php

namespace App\Http\Requests\DiscipleshipPeopleTree;

use Illuminate\Foundation\Http\FormRequest;

class PeopleTreeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
