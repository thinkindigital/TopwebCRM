import fs from 'node:fs';
import { execSync } from 'node:child_process';
import { e2eFixture, fixturePath } from './fixture';

export default function globalSetup(): void {
  const command = process.env.E2E_FIXTURE_SETUP_COMMAND
    ?? 'php artisan topweb-chat:e2e-fixtures --install --json';

  try {
    const output = execSync(command, {
      env: process.env,
      shell: '/bin/bash',
      stdio: ['ignore', 'pipe', 'pipe'],
      encoding: 'utf8',
    }).trim();
    const fixture = JSON.parse(output);
    fs.writeFileSync(fixturePath(), `${JSON.stringify(fixture, null, 2)}\n`, { mode: 0o600 });
    e2eFixture();
  } catch (error) {
    const cleanup = process.env.E2E_FIXTURE_CLEANUP_COMMAND
      ?? 'php artisan topweb-chat:e2e-fixtures --cleanup --json';
    try {
      execSync(cleanup, {
        env: process.env,
        shell: '/bin/bash',
        stdio: 'ignore',
      });
    } catch {
      // Preserve the original setup failure; the cleanup command is best effort.
    }
    fs.rmSync(fixturePath(), { force: true });
    throw new Error(`TopwebChat E2E fixture setup failed: ${String(error)}`);
  }
}
