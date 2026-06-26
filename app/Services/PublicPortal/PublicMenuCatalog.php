<?php

namespace App\Services\PublicPortal;

use App\Enums\PublicMaterialMenuKey;

class PublicMenuCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function cards(): array
    {
        return array_map(
            fn (array $card): array => $this->normalizeCard($card),
            [
                [
                    'title' => 'Jurnal Temu DG',
                    'title_lines' => ['Jurnal', 'Temu DG'],
                    'href' => route('public.dg.branch', [], false),
                    'is_primary' => true,
                ],
                [
                    'title' => 'Jurnal Umpan Balik Anggota',
                    'title_lines' => ['Jurnal', 'Umpan Balik', 'Anggota'],
                    'href' => route('public.member-feedback.branch', [], false),
                    'is_primary' => true,
                    'cta' => 'Isi Jurnal',
                ],
                [
                    'title' => 'Materi DG-1',
                    'sub' => '(BePI)',
                    'href' => route('materials.show', ['menu' => PublicMaterialMenuKey::MateriDg1->value], false),
                ],
                [
                    'title' => 'Materi DG-2',
                    'sub' => '(BOI)',
                    'href' => route('materials.show', ['menu' => PublicMaterialMenuKey::MateriDg2->value], false),
                ],
                [
                    'title' => 'Materi DG-3',
                    'href' => route('materials.show', ['menu' => PublicMaterialMenuKey::MateriDg3->value], false),
                ],
                [
                    'title' => 'Meditasi Injil',
                    'sub' => '(BePI)',
                    'href' => route('materials.show', ['menu' => PublicMaterialMenuKey::MeditasiInjil->value], false),
                ],
                [
                    'title' => 'Handbook & Perjanjian Kelompok',
                    'title_lines' => ['Handbook &', 'Perjanjian', 'Kelompok'],
                    'href' => route('materials.show', ['menu' => PublicMaterialMenuKey::HandbookPerjanjianKelompok->value], false),
                    'tile_class' => 'is-wide-mobile',
                ],
                [
                    'title' => 'Unggah Pertanyaan Sulit',
                    'title_lines' => ['Unggah', 'Pertanyaan', 'Sulit'],
                    'href' => route('public.difficult-question.submit', [], false),
                    'tile_class' => 'is-half',
                ],
                [
                    'title' => 'Jawaban Pertanyaan Sulit',
                    'title_lines' => ['Jawaban', 'Pertanyaan', 'Sulit'],
                    'href' => route('public.difficult-question.answer', [], false),
                    'tile_class' => 'is-half',
                ],
            ],
        );
    }

    public function emptyMenuLabel(string $menuKey): string
    {
        $menu = PublicMaterialMenuKey::fromKey($menuKey);
        if ($menu instanceof PublicMaterialMenuKey) {
            return $menu->label();
        }

        $labels = [
            'jurnal_umpan_balik_anggota' => 'Jurnal Umpan Balik Anggota',
            'unggah_pertanyaan_sulit' => 'Unggah Pertanyaan Sulit',
            'jawaban_pertanyaan_sulit' => 'Jawaban Pertanyaan Sulit',
        ];

        return $labels[trim($menuKey)] ?? 'Menu';
    }

    /**
     * @param array<string, mixed> $card
     * @return array<string, mixed>
     */
    private function normalizeCard(array $card): array
    {
        $title = trim((string) ($card['title'] ?? 'Menu'));
        $isPrimary = ! empty($card['is_primary']);
        $tileClass = $isPrimary ? 'public-menu-tile is-primary' : 'public-menu-tile';
        $extraTileClass = trim((string) ($card['tile_class'] ?? ''));
        if ($extraTileClass !== '') {
            $tileClass .= ' ' . $extraTileClass;
        }

        $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        $titleClass = 'public-menu-tile-title';
        if ($titleLength >= 24) {
            $titleClass .= ' is-xlong';
        } elseif ($titleLength >= 18) {
            $titleClass .= ' is-long';
        }

        $titleLines = [];
        if (is_array($card['title_lines'] ?? null)) {
            foreach ($card['title_lines'] as $titleLine) {
                $titleLine = trim((string) $titleLine);
                if ($titleLine !== '') {
                    $titleLines[] = $titleLine;
                }
            }
        }

        return [
            'title' => $title,
            'title_lines' => $titleLines,
            'sub' => trim((string) ($card['sub'] ?? '')),
            'href' => trim((string) ($card['href'] ?? '#')),
            'tile_class' => $tileClass,
            'title_class' => $titleClass,
            'cta' => trim((string) ($card['cta'] ?? ($isPrimary ? 'Pilih Cabang' : 'Buka Menu'))),
        ];
    }
}
