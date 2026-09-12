<?php

namespace App\Http\Requests\DiscipleshipPeopleTree;

use App\Services\Auth\CurrentUserContext;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Foundation\Http\FormRequest;

class DeletePeopleTreeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);
        $scope = app(CurrentDiscipleshipScope::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('people_tree')
            && $context->canUseAction('delete_group')
            && ! $scope->isReadOnly()
            && $scope->selectedBranchId() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['group' => $this->route('group')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'group' => ['required', 'integer', 'min:1'],
        ];
    }

    public function branchId(): int
    {
        return (int) app(CurrentDiscipleshipScope::class)->selectedBranchId();
    }

    public function groupId(): int
    {
        return (int) $this->validated('group');
    }
}
