import { afterEach, describe, expect, it, vi } from 'vitest'
import { getLeague } from './client'

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('api client errors', () => {
  it('reports unreachable API failures with an actionable message', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Load failed')))

    await expect(getLeague('L1')).rejects.toThrow(
      'API server is not reachable. Check that it is running at http://127.0.0.1:8000.',
    )
  })

  it('preserves server-provided error messages', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      statusText: 'Unprocessable Entity',
      json: async () => ({ message: 'League name is required.' }),
    }))

    await expect(getLeague('L1')).rejects.toThrow('League name is required.')
  })

  it('flattens server-side validation errors when no top-level message is given', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: false,
      status: 422,
      statusText: 'Unprocessable Entity',
      json: async () => ({
        errors: { teams: ['Provide an even number of teams.'], name: ['The name is required.'] },
      }),
    }))

    await expect(getLeague('L1')).rejects.toThrow('Provide an even number of teams. The name is required.')
  })

  it('reports a clean message instead of leaking validation detail on a malformed success body', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      statusText: 'OK',
      json: async () => ({ not: 'a snapshot' }),
    }))

    await expect(getLeague('L1')).rejects.toThrow('Received an unexpected response from the server.')
  })
})
