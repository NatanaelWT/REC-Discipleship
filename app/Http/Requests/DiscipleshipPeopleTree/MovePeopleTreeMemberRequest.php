<?php

namespace App\Http\Requests\DiscipleshipPeopleTree;

use App\Services\Auth\CurrentUserContext;
use App\Services\Discipleship\CurrentDiscipleshipScope;
use Illuminate\Foundation\Http\FormRequest;

class MovePeopleTreeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(CurrentUserContext::class);
        $scope = app(CurrentDiscipleshipScope::class);

        return $context->isLoggedIn()
            && $context->canAccessPage('people_tree')
            && $context->canUseAction('move_person_group')
            && ! $scope->isReadOnly()
            && $scope->selectedBranchId() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'min:1'],
            'from_group_id' => ['required', 'integer', 'min:1'],
            'to_group_id' => ['required', 'integer', 'min:1', 'different:from_group_id'],
            'return_page' => ['nullable', 'in:people_tree,discipleship_dashboard,groups_list,people_list'],
        ];
    }

    public function branchId(): int
    {
        return (int) app(CurrentDiscipleshipScope::class)->selectedBranchId();
    }

    public function personId(): int
    {
        return (int) $this->validated('person_id');
    }

    public function fromGroupId(): int
    {
        return (int) $this->validated('from_group_id');
    }

    public function toGroupId(): int
    {
        return (int) $this->validated('to_group_id');
    }
}
