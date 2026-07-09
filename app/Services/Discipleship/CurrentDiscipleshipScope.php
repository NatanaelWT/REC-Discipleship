<?php

namespace App\Services\Discipleship;

use App\Services\Auth\CurrentUserContext;
use App\Services\Branches\BranchCatalog;
use Illuminate\Http\Request;

class CurrentDiscipleshipScope
{
    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $optionsById;

    /** @var array<int, array{id:int,slug:string,label:string}> */
    private array $publicOptionsById;

    /** @var array<int, int> */
    private array $branchIds;

    private ?int $selectedBranchId;

    private bool $allBranches;

    private bool $readOnly;

    public function __construct(
        private readonly Request $request,
        private readonly CurrentUserContext $user,
        private readonly BranchCatalog $branches,
    ) {
        $this->publicOptionsById = $branches->activeOptionsById();
        $this->optionsById = $user->isDeveloper()
            ? $branches->developerOptionsById()
            : $this->publicOptionsById;
        $this->readOnly = true;
        $this->resolve();
    }

    /** @return array<int, int> */
    public function branchIds(): array
    {
        return $this->branchIds;
    }

    public function selectedBranchId(): ?int
    {
        return $this->selectedBranchId;
    }

    public function includesAllBranches(): bool
    {
        return $this->allBranches;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function allows(int $branchId): bool
    {
        return in_array($branchId, $this->branchIds, true);
    }

    public function selectedLabel(): string
    {
        if ($this->allBranches) {
            return 'Semua Cabang';
        }

        return $this->optionsById[$this->selectedBranchId ?? 0]['label'] ?? 'Tanpa cabang';
    }

    public function selectedSlug(): string
    {
        if ($this->allBranches) {
            return 'all';
        }

        return $this->optionsById[$this->selectedBranchId ?? 0]['slug'] ?? '';
    }

    /** @return array<int, array{id:int,slug:string,label:string}> */
    public function branchOptions(): array
    {
        return array_values($this->optionsById);
    }

    /** @return array<int, array{id:int,slug:string,label:string}> */
    public function optionsById(): array
    {
        return $this->optionsById;
    }

    private function resolve(): void
    {
        if ($this->user->isDiscipleshipBranch()) {
            $branchId = $this->user->branchId();
            $this->selectedBranchId = $branchId !== null && isset($this->optionsById[$branchId]) ? $branchId : null;
            $this->branchIds = $this->selectedBranchId !== null ? [$this->selectedBranchId] : [];
            $this->allBranches = false;
            $this->readOnly = false;

            return;
        }

        $selected = $this->selectionFromRequest();
        if ($selected === null) {
            $stored = $this->request->session()->get('central_rekap_branch_id', 'all');
            $selected = is_numeric($stored) && isset($this->optionsById[(int) $stored]) ? (int) $stored : 'all';
        }

        if ($selected === 'all') {
            $this->selectedBranchId = null;
            $this->branchIds = array_map('intval', array_keys($this->publicOptionsById));
            $this->allBranches = true;
            $this->readOnly = true;

            return;
        }

        $this->selectedBranchId = $selected;
        $this->branchIds = [$selected];
        $this->allBranches = false;
        $this->readOnly = ! ($this->user->isDeveloper() && $this->branches->isInactiveId($selected));
    }

    private function selectionFromRequest(): int|string|null
    {
        if (! $this->request->query->has('branch_id')) {
            return null;
        }

        $input = trim((string) $this->request->query('branch_id', 'all'));
        $selection = 'all';
        if ($input !== 'all' && ctype_digit($input) && isset($this->optionsById[(int) $input])) {
            $selection = (int) $input;
        }

        if ($this->request->session()->get('central_rekap_branch_id') !== $selection) {
            $this->request->session()->put('central_rekap_branch_id', $selection);
        }

        return $selection;
    }
}
