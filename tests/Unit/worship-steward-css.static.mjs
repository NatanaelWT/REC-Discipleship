import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const css = await readFile('public/assets/style.css', 'utf8');
const hasRule = (pattern, name) => assert.match(css, pattern, name);

hasRule(/\.worship-steward-table-wrap\s*\{[^}]*overflow-x:\s*auto;[^}]*overscroll-behavior-x:\s*contain;/s, 'The planner wrapper must contain horizontal overscroll.');
hasRule(/\.worship-steward-planner-table\s*\{[^}]*width:\s*100%;[^}]*min-width:\s*1100px;/s, 'The planner must retain desktop column geometry.');
hasRule(/\.worship-steward-planner-table thead th\s*\{[^}]*padding:\s*13px 12px;[^}]*font-size:\s*12px;[^}]*font-weight:\s*700;/s, 'Planner headers must remain readable.');
hasRule(/\.worship-steward-editor-card \.worship-steward-planner-table tbody th\s*\{[^}]*position:\s*sticky;[^}]*left:\s*0;[^}]*min-width:\s*210px;[^}]*padding:\s*12px;/s, 'Role labels must remain usable while scrolling.');
hasRule(/\.worship-steward-editor-card \.worship-steward-planner-table td\s*\{[^}]*min-width:\s*155px;[^}]*padding:\s*8px !important;/s, 'Planner cells must retain usable spacing.');
hasRule(/\.worship-steward-cell\s*\{[^}]*min-height:\s*40px;[^}]*border-radius:\s*10px;[^}]*padding:\s*9px 10px;[^}]*font-size:\s*13px;/s, 'Textarea controls must retain accessible dimensions.');
hasRule(/\.worship-steward-(?:duo|training)-input\s*\{[^}]*min-height:\s*40px;[^}]*border-radius:\s*10px;[^}]*padding:\s*9px 10px;[^}]*font-size:\s*13px;/s, 'Dual and date controls must retain matching dimensions.');
hasRule(/\.worship-steward-training-preview\s*\{[^}]*font-size:\s*11px;[^}]*line-height:\s*1\.35;/s, 'Training preview must remain readable.');
hasRule(/@media \(max-width: 768px\)\s*\{\s*\.worship-steward-editor-card \.worship-steward-planner-table tbody th\s*\{[^}]*position:\s*static;/s, 'Sticky role labels must be disabled on mobile.');

console.log('Worship steward CSS static assertions passed.');
