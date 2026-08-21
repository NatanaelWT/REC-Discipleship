import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const [panel, app] = await Promise.all([
  readFile('resources/views/discipleship/people-tree/panel.blade.php', 'utf8'),
  readFile('public/assets/app.js', 'utf8'),
]);

assert.match(panel, /data-leader-id=\\\"" \. h\(\$existingGroupLeaderId\)/, 'Edit-group options must expose the primary leader ID.');
assert.match(panel, /leader_cannot_join_own_group/, 'The server error must have a people-tree alert mapping.');
assert.match(app, /const filterOwnLeaderGroupOptions =/, 'Edit modal must filter own-leader groups.');
assert.match(app, /opt\.hidden = isBlocked;\s*opt\.disabled = isBlocked;/, 'Blocked groups must be hidden and disabled.');
assert.match(app, /filterOwnLeaderGroupOptions\(groupSelect, personId\);/, 'Edit modal must filter using the edited person ID.');
assert.match(app, /if \(groupSelect\.selectedOptions\[0\]\?\.disabled\) groupSelect\.value = '';/, 'Blocked current group selection must be cleared.');

console.log('dgv2 own-group UI static assertions passed.');
