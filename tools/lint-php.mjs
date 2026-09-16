/**
 * Parses every PHP file in the theme and reports syntax errors.
 *
 * `php -l` is the real thing, but it needs PHP installed. This uses a PHP
 * parser written in JavaScript, so a syntax error is caught before the theme
 * is ever uploaded.
 *
 * Run from the theme root:  npm run lint:php
 */
import fs from 'fs';
import path from 'path';
import { Engine } from 'php-parser';

const ROOT = process.cwd();
const SKIP = new Set(['node_modules', '.git', 'vendor', 'assets', 'languages', 'tools']);

const parser = new Engine({
  parser: { suppressErrors: false, version: 704 },
  ast: { withPositions: true },
});

function phpFiles(dir, found = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (!SKIP.has(entry.name)) phpFiles(path.join(dir, entry.name), found);
    } else if (entry.name.endsWith('.php')) {
      found.push(path.join(dir, entry.name));
    }
  }
  return found;
}

let failed = 0;
const files = phpFiles(ROOT);

for (const file of files) {
  const relative = path.relative(ROOT, file).replace(/\\/g, '/');

  try {
    parser.parseCode(fs.readFileSync(file, 'utf8'), relative);
    console.log('  ok   ' + relative);
  } catch (error) {
    failed++;
    console.log('  FAIL ' + relative + ' — ' + error.message.split('\n')[0]);
  }
}

console.log(`\n${files.length - failed}/${files.length} files parse cleanly.`);

if (failed) process.exit(1);
