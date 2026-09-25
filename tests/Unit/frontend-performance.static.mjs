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
assert.match(discipleship, /const fragmentRequests = new Map\(\)/, 'Workspace tab requests must keep an in-memory cache.');
assert.match(discipleship, /const requestFragment = [\s\S]{0,700}?fragmentRequests\.get\(cacheKey\)/, 'Workspace tab requests must reuse cached fetches.');
assert.match(discipleship, /connection\?\.saveData[\s\S]{0,200}?slow-2g[\s\S]{0,100}?2g/, 'Tab prefetch must respect data saver and slow connections.');
assert.match(discipleship, /addEventListener\('pointerenter', prefetch/, 'Workspace tabs must prefetch from pointer intent.');
assert.match(discipleship, /requestIdleCallback\(run, \{ timeout: 1800 \}\)/, 'Workspace tabs must prefetch during browser idle time.');
assert.match(discipleship, /const initializersByTab = \{[\s\S]{0,900}?feedback: \[setupMemberFeedbackRecap\]/, 'Workspace panels must initialize only tab-specific features.');
assert.match(css, /\.dashboard-lazy-shell\s*\{[\s\S]{0,160}?content-visibility: auto;/, 'Off-screen dashboard sections must skip rendering work.');
assert.match(css, /page-tree-v2[\s\S]{0,1000}?overflow-y: visible !important;/, 'People tree workspace must use document scrolling.');

console.log('Frontend performance static assertions passed.');
