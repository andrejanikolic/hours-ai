// Global test setup. Runs before every test file.
//
// `fetch` is mocked per-suite in the API tests; here we just make sure a global
// stub exists so importing `useApi` never blows up under jsdom.
import { vi } from 'vitest'

if (!globalThis.fetch) {
  globalThis.fetch = vi.fn() as unknown as typeof fetch
}
