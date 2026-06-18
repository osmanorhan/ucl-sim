import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
  getEvaluation,
  playWeek,
} from '../api/client'
import type { LeagueSnapshot } from '../types/league'
import { useLeagueStore } from './league'

vi.mock('../api/client', () => ({
  createLeague: vi.fn(),
  getLeague: vi.fn(),
  playWeek: vi.fn(),
  playAll: vi.fn(),
  updateMatch: vi.fn(),
  getEvaluation: vi.fn(),
}))

function snapshotOf(id: string, version: number): LeagueSnapshot {
  return {
    version,
    league: { id, name: 'Champions League', seed: 42, currentWeek: 0, totalWeeks: 6 },
    table: [],
    fixtures: [],
    predictionAvailability: { available: false, availableAfterCompletedWeeks: 4 },
    predictions: null,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('applySnapshot version guard', () => {
  it('rejects a stale snapshot for the same league', () => {
    const store = useLeagueStore()

    store.applySnapshot(snapshotOf('L1', 2))
    store.applySnapshot(snapshotOf('L1', 1))

    expect(store.snapshot?.version).toBe(2)
  })

  it('accepts a newer snapshot for the same league', () => {
    const store = useLeagueStore()

    store.applySnapshot(snapshotOf('L1', 1))
    store.applySnapshot(snapshotOf('L1', 2))

    expect(store.snapshot?.version).toBe(2)
  })

  it('replaces wholesale when the league changes, regardless of version', () => {
    const store = useLeagueStore()

    store.applySnapshot(snapshotOf('L1', 9))
    store.applySnapshot(snapshotOf('L2', 1))

    expect(store.snapshot?.league.id).toBe('L2')
    expect(store.snapshot?.version).toBe(1)
  })
})

describe('action error handling', () => {
  it('funnels a failed play-week into store.error and clears pending', async () => {
    const store = useLeagueStore()
    store.applySnapshot(snapshotOf('L1', 1))

    vi.mocked(playWeek).mockRejectedValueOnce(new Error('season is complete'))

    await store.playNextWeek()

    expect(store.error).toBe('season is complete')
    expect(store.pending).toBe(false)
    expect(store.pendingAction).toBeNull()
  })

  it('reports a missing league instead of throwing raw', async () => {
    const store = useLeagueStore()

    await store.playNextWeek()

    expect(store.error).toBe('Create a league first.')
    expect(vi.mocked(playWeek)).not.toHaveBeenCalled()
  })
})

describe('evaluate', () => {
  it('stores scorecards on success', async () => {
    const store = useLeagueStore()
    store.applySnapshot(snapshotOf('L1', 1))

    vi.mocked(getEvaluation).mockResolvedValueOnce({
      leagueId: 'L1',
      scorecards: [
        { strategy: 'monte-carlo', brier: 0.1, logLoss: 0.2, meanLatencyMs: 1.5, deterministic: false },
      ],
    })

    await store.evaluate()

    expect(store.evaluation).toHaveLength(1)
    expect(store.evaluating).toBe(false)
    expect(store.error).toBeNull()
  })

  it('captures a benchmark failure', async () => {
    const store = useLeagueStore()
    store.applySnapshot(snapshotOf('L1', 1))

    vi.mocked(getEvaluation).mockRejectedValueOnce(new Error('benchmark failed'))

    await store.evaluate()

    expect(store.error).toBe('benchmark failed')
    expect(store.evaluating).toBe(false)
  })
})
