<?php

declare(strict_types=1);

function dgv2_identity_sources(array $members, array $mskClasses): array
{
    return $members;
}

function dgv2_canonical_identity_source_id(string $sourceId, array $mskClasses): string
{
    return trim($sourceId);
}

function dgv2_find_identity(array $identityById, string $memberId): array
{
    return $identityById[$memberId] ?? [];
}

function dgv2_is_current_period(array $row): bool
{
    return ($row['status'] ?? 'active') === 'active' && ($row['end_date'] ?? '') === '';
}

function temporary_model_id(string $type): string
{
    return 'new_'.$type.'_static';
}

function now_iso(): string
{
    return '2026-08-21T00:00:00+00:00';
}

function today_date(): string
{
    return '2026-08-21';
}

function discipleship_group_stage_value(mixed $group): string
{
    return (string) ($group['stage'] ?? 'DG 1');
}

function dgv2_group_active_member_ids(array $model, string $groupId): array
{
    $ids = [];
    foreach ($model['group_memberships'] as $membership) {
        if (is_array($membership) && dgv2_is_current_period($membership) && ($membership['group_id'] ?? '') === $groupId) {
            $ids[] = (string) ($membership['person_id'] ?? '');
        }
    }
    return $ids;
}

function dgv2_sync_group_memberships(array &$model, string $groupId, array $memberIds, string $stage): void
{
    foreach ($memberIds as $personId) {
        if ($personId !== '') {
            $model['group_memberships'][] = ['id' => temporary_model_id('membership'), 'person_id' => $personId, 'group_id' => $groupId, 'status' => 'active', 'end_date' => ''];
        }
    }
}

require __DIR__.'/../../app/Support/Helpers/dgv2_save_person_single.php';

function static_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function model_with_leadership(string $leaderPersonId, string $leaderGroupId, string $status = 'active'): array
{
    return [
        'discipleship_persons' => [['id' => 'person-1', 'member_id' => 'member-1', 'full_name' => 'Pemimpin', 'status' => 'active', 'notes' => 'lama']],
        'discipleship_groups' => [['id' => 'group-own', 'stage' => 'DG 1'], ['id' => 'group-other', 'stage' => 'DG 1']],
        'group_memberships' => [['id' => 'membership-old', 'person_id' => 'person-1', 'group_id' => 'group-old', 'status' => 'active', 'end_date' => '']],
        'group_leaderships' => [['id' => 'leadership-1', 'group_id' => $leaderGroupId, 'leader_person_id' => $leaderPersonId, 'role' => 'co_leader', 'status' => $status, 'end_date' => $status === 'active' ? '' : '2026-08-01']],
    ];
}

$members = ['member-1' => ['id' => 'member-1', 'full_name' => 'Pemimpin', 'whatsapp' => '', 'gender' => '', 'completed_msk' => true]];
$payload = ['id' => 'person-1', 'member_id' => 'member-1', 'group_id' => 'group-own', 'notes' => 'baru', 'leader_id' => ''];

$model = model_with_leadership('person-1', 'group-own');
$before = serialize($model);
$result = dgv2_save_person_single($model, $payload, $members, []);
static_assert($result === ['ok' => false, 'error' => 'leader_cannot_join_own_group'], 'Current leader must be rejected with the authoritative error code.');
static_assert(serialize($model) === $before, 'Rejected current leader must leave the model byte-for-byte unchanged.');

$model = model_with_leadership('person-1', 'group-own', 'closed');
$result = dgv2_save_person_single($model, $payload, $members, []);
static_assert(($result['ok'] ?? false) === true, 'Closed leadership must remain eligible for membership.');

$model = model_with_leadership('person-1', 'group-own');
$payload['group_id'] = 'group-other';
$result = dgv2_save_person_single($model, $payload, $members, []);
static_assert(($result['ok'] ?? false) === true, 'A leader must remain eligible to join a different group.');

echo "dgv2_save_person_single own-group static assertions passed.\n";
