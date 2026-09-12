import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const [modal, row, app, css] = await Promise.all([
  readFile('resources/views/discipleship/people-tree/partials/group-history-modal.blade.php', 'utf8'),
  readFile('resources/views/discipleship/groups/partials/row.blade.php', 'utf8'),
  readFile('public/assets/app.js', 'utf8'),
  readFile('public/assets/style.css', 'utf8'),
]);

for (const action of ['add_member', 'complete_group', 'reactivate_group', 'upgrade_group']) {
  const button = new RegExp(`<button\\b(?=[^>]*class="[^"]*\\bis-hidden\\b)(?=[^>]*data-tree-v2-action-do="${action}")(?=[^>]*\\bhidden\\b)(?=[^>]*\\bdisabled\\b)[^>]*>`);
  assert.match(modal, button, `${action} must be hidden and disabled until its DG state is known.`);
}

assert.match(row, /data-group-detail-status="\{\{ \$actionStatus \}\}"[\s\S]{0,120}?data-group-detail-has-child="\{\{ \$hasChildGroup \? '1' : '0' \}\}"/, 'Group-list triggers must expose the exact status and child-group state.');
assert.match(css, /\.tree-v2-profile-action\.is-hidden,[\s\S]{0,240}?display:\s*none;/, 'Hidden profile actions must override the base inline-flex display.');
assert.match(css, /\.tree-v2-action-buttons \.btn\.is-hidden,[\s\S]{0,160}?display:\s*none;/, 'Hidden modal actions must override button display rules.');
assert.match(app, /add_member:\s*isActive,[\s\S]{0,180}?complete_group:\s*isActive,[\s\S]{0,180}?reactivate_group:\s*status === 'completed' && !hasChildGroup,[\s\S]{0,180}?upgrade_group:\s*isActive && progress !== 'DG 3'/, 'Group-list actions must follow DG status, progress, and child-group state.');
assert.match(app, /const canReactivateGroup =[\s\S]{0,320}?!nodeData\.hasChildGroup[\s\S]{0,180}?=== 'completed';/, 'Tree reactivation must require a completed DG without a child group.');
assert.match(app, /button\.classList\.toggle\('is-hidden', !visible\);\s*button\.hidden = !visible;\s*button\.disabled = !visible;/, 'Tree actions must synchronize visible, hidden, and disabled states.');

console.log('DG action visibility static assertions passed.');
