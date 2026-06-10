import { execSync } from 'node:child_process';

/**
 * Resets the seeded database before a suite that exercises a real write
 * (e.g. a real `PUT /serving-times/replace` instead of a mocked one).
 *
 * Most specs mock both parse and replace and never need this. Call it from a
 * `globalSetup` or `test.beforeAll` only in specs that assert persistence.
 */
export function resetDatabase(): void {
  execSync('docker compose exec -T app php artisan migrate:fresh --seed', {
    stdio: 'inherit',
    cwd: '..',
  });
}
