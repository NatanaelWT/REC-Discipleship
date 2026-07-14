<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ResponsiveAuditSeeder extends Seeder
{
    public const PASSWORD = 'responsive-test';

    public function run(): void
    {
        if (! app()->environment('testing') || DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('ResponsiveAuditSeeder may only run with testing SQLite.');
        }

        $branchIds = [];
        foreach (['Kutisari', 'GM', 'Darmo', 'Merr', 'Batam', 'Nginden'] as $label) {
            $branch = Branch::query()->updateOrCreate(['label' => $label], [
                'is_active' => true,
                'camp_gap_participant_target' => 24,
                'msk_completion_target' => 18,
                'dg1_completion_target' => 12,
                'dg2_completion_target' => 8,
                'dg3_completion_target' => 4,
            ]);
            $branchIds[strtolower($label)] = (int) $branch->getKey();
        }

        foreach ([
            ['responsive_branch', 'pemuridan_cabang', $branchIds['kutisari']],
            ['responsive_central', 'pemuridan_pusat', null],
            ['responsive_steward', 'pelayan', null],
            ['responsive_developer', 'developer', null],
        ] as [$username, $scope, $branchId]) {
            User::query()->create([
                'username' => $username,
                'password' => Hash::make(self::PASSWORD),
                'branch_id' => $branchId,
                'access_scope' => $scope,
                'is_active' => true,
            ]);
        }

        $people = collect([
            ['Nama Peserta Dengan Teks Sangat Panjang Untuk Pengujian Responsif', range(1, 12)],
            ['Adelynn Regina Gunawan', [1, 2, 3, 4]],
            ['Agnes Pramayu', [1, 2]],
            ['Christopher Jonathan Setiawan', range(1, 12)],
            ['Maria Magdalena', [1, 2, 3, 4, 5, 6]],
            ['Yohanes Kurniawan', range(1, 12)],
            ['Stefanny Soesanto', [1]],
            ['Michelle Heidy Widjaya', range(1, 12)],
        ])->map(function (array $row, int $index) use ($branchIds): Person {
            return Person::query()->create([
                'branch_id' => $branchIds['kutisari'],
                'full_name' => $row[0],
                'gender' => $index % 2 === 0 ? 'Perempuan' : 'Laki-laki',
                'whatsapp' => '081200000'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'batch_month' => '2026-07',
                'status' => 'active',
                'journey_bridge_status' => $index % 3 === 0 ? 'sudah' : 'belum',
                'session_numbers' => $row[1],
                'photos' => [],
            ]);
        });

        $group = new DiscipleshipGroup;
        $group->forceFill([
            'branch_id' => $branchIds['kutisari'],
            'status' => 'active',
            'stage' => 'DG 1',
            'notes' => 'Data khusus untuk pengujian responsivitas.',
        ])->save();

        foreach ($people as $index => $person) {
            DiscipleshipGroupPerson::query()->create([
                'branch_id' => $branchIds['kutisari'],
                'discipleship_group_id' => $group->getKey(),
                'person_id' => $person->getKey(),
                'role' => $index === 0 ? 'leader' : 'member',
                'stage' => 'DG 1',
                'status' => 'active',
                'started_on' => '2026-01-01',
            ]);
        }
    }
}
