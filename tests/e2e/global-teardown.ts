import { execSync } from 'node:child_process';
import fs from 'node:fs';
import { fixturePath } from './fixture';

export default function globalTeardown(): void {
  const command = process.env.E2E_FIXTURE_CLEANUP_COMMAND
    ?? 'php artisan topweb-chat:e2e-fixtures --cleanup --json';

  try {
    execSync(command, {
      env: process.env,
      shell: '/bin/bash',
      stdio: ['ignore', 'pipe', 'pipe'],
      encoding: 'utf8',
    });
  } finally {
    fs.rmSync(fixturePath(), { force: true });
  }
}
