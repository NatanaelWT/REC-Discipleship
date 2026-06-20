<?php

namespace App\Enums;

enum UserAccessRole: string
{
    case Developer = 'developer';
    case DiscipleshipBranch = 'pemuridan_cabang';
    case DiscipleshipCentral = 'pemuridan_pusat';
    case Steward = 'pelayan';

    public static function fromStoredValue(string $value): self
    {
        return match (strtolower(trim($value))) {
            'developer' => self::Developer,
            'pemuridan_pusat', 'central_discipleship_readonly' => self::DiscipleshipCentral,
            'pelayan', 'worship_only' => self::Steward,
            'pemuridan_cabang', 'discipleship_branch', 'branch' => self::DiscipleshipBranch,
            default => self::DiscipleshipBranch,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer',
            self::DiscipleshipBranch => 'Pemuridan Cabang',
            self::DiscipleshipCentral => 'Pusat Pemuridan',
            self::Steward => 'Pelayan',
        };
    }

    public function requiresBranch(): bool
    {
        return $this === self::DiscipleshipBranch;
    }

    public function canAccessDiscipleship(): bool
    {
        return in_array($this, [self::Developer, self::DiscipleshipBranch, self::DiscipleshipCentral], true);
    }

    public function canAccessStewardship(): bool
    {
        return in_array($this, [self::Developer, self::Steward], true);
    }
}
