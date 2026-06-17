import { describe, expect, it } from 'vitest'
import { ResultOrigin, WeekState, isManualResult, weekState } from './league'
import type { Match, WeekFixtures } from '../types/league'

function match(overrides: Partial<Match> = {}): Match {
  return {
    id: 'm1',
    homeTeamId: 'a',
    awayTeamId: 'b',
    homeGoals: null,
    awayGoals: null,
    played: false,
    origin: null,
    ...overrides,
  }
}

function week(matches: Match[]): WeekFixtures {
  return { week: 1, matches }
}

describe('weekState', () => {
  it('is complete when every match is played', () => {
    const state = weekState(week([
      match({ id: 'm1', played: true }),
      match({ id: 'm2', played: true }),
    ]))

    expect(state).toBe(WeekState.Complete)
  })

  it('is partial when some but not all matches are played', () => {
    const state = weekState(week([
      match({ id: 'm1', played: true }),
      match({ id: 'm2', played: false }),
    ]))

    expect(state).toBe(WeekState.Partial)
  })

  it('is pending when no match is played', () => {
    const state = weekState(week([match({ id: 'm1' }), match({ id: 'm2' })]))

    expect(state).toBe(WeekState.Pending)
  })
})

describe('isManualResult', () => {
  it('is true only for a manual origin', () => {
    expect(isManualResult(match({ origin: ResultOrigin.Manual }))).toBe(true)
    expect(isManualResult(match({ origin: ResultOrigin.Simulated }))).toBe(false)
    expect(isManualResult(match({ origin: null }))).toBe(false)
  })
})
