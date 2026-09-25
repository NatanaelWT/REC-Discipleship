import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const [entry, discipleship, css] = await Promise.all([
  readFile(new URL('../../resources/js/app.js', import.meta.url), 'utf8'),
  readFile(new URL('../../public/assets/app.js', import.meta.url), 'utf8'),
  readFile(new URL('../../public/assets/style.css', import.meta.url), 'utf8'),
]);

assert.doesNotMatch(entry, /new MutationObserver\(/, 'Frontend entry must not scan every DOM mutation.');
assert.match(entry, /pointerdown[\s\S]{0,500}?data-client-image-variants/, 'Image optimization must load from direct user interaction.');
assert.match(discipleship, /sectionObserver = new IntersectionObserver[\s\S]{0,500}?rootMargin: '420px 0px'/, 'Dashboard sections must load near the viewport.');
assert.match(discipleship, /document\.hidden[\s\S]{0,250}?setTimeout\(scheduleClock/, 'Live clocks must pause in hidden tabs.');
assert.match(css, /\.dashboard-lazy-shell\s*\{[\s\S]{0,160}?content-visibility: auto;/, 'Off-screen dashboard sections must skip rendering work.');
assert.match(css, /page-tree-v2[\s\S]{0,1000}?overflow-y: visible !important;/, 'People tree workspace must use document scrolling.');

console.log('Frontend performance static assertions passed.');
