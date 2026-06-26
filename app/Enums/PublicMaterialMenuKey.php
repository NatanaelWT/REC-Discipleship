<?php

namespace App\Enums;

enum PublicMaterialMenuKey: string
{
    case MateriDg1 = 'materi_dg_1';
    case MateriDg2 = 'materi_dg_2';
    case MateriDg3 = 'materi_dg_3';
    case MeditasiInjil = 'meditasi_injil';
    case HandbookPerjanjianKelompok = 'handbook_perjanjian_kelompok';

    public static function fromKey(string $key): ?self
    {
        return self::tryFrom(trim($key));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $menu): string => $menu->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::MateriDg1 => 'Materi DG-1 (BePI)',
            self::MateriDg2 => 'Materi DG-2 (BOI)',
            self::MateriDg3 => 'Materi DG-3',
            self::MeditasiInjil => 'Meditasi Injil (BePI)',
            self::HandbookPerjanjianKelompok => 'Handbook & Perjanjian Kelompok',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::MateriDg1 => 'Berpusat Pada Injil',
            self::MateriDg2 => 'Berubah Oleh Injil',
            self::MateriDg3 => 'Melayani Dengan Injil',
            self::MeditasiInjil => 'Merenungkan Injil Setiap Hari',
            self::HandbookPerjanjianKelompok => 'Bertumbuh Dalam Komitmen Kelompok',
        };
    }

    public function folder(): string
    {
        return match ($this) {
            self::MateriDg1 => 'Materi-DG/DG-1',
            self::MateriDg2 => 'Materi-DG/DG-2',
            self::MateriDg3 => 'Materi-DG/DG-3',
            self::MeditasiInjil => 'Materi-DG/Meditasi-Injil',
            self::HandbookPerjanjianKelompok => 'Materi-DG/Handbook-Perjanjian-Kelompok',
        };
    }

    public function isDgSessionMenu(): bool
    {
        return in_array($this, [self::MateriDg1, self::MateriDg2, self::MateriDg3], true);
    }
}
