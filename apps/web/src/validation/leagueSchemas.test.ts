import { describe, expect, it } from 'vitest'
import {
  CreateLeaguePayloadSchema,
  LeagueSnapshotSchema,
  UpdateMatchPayloadSchema,
} from './leagueSchemas'

function teams(count: number) {
  return Array.from({ length: count }, (_, index) => ({
    id: `t${index}`,
    name: `Team ${index}`,
    power: 50,
  }))
}

describe('CreateLeaguePayloadSchema', () => {
  it('accepts an even, uniquely-keyed squad', () => {
    const result = CreateLeaguePayloadSchema.safeParse({
      name: 'Champions League',
      seed: -7,
      teams: teams(4),
    })

    expect(result.success).toBe(true)
  })

  it('rejects an odd number of teams on the teams path', () => {
    const result = CreateLeaguePayloadSchema.safeParse({
      name: 'Champions League',
      seed: 1,
      teams: teams(3),
    })

    expect(result.success).toBe(false)
    expect(result.error?.issues.some((issue) => issue.path.join('.') === 'teams')).toBe(true)
  })

  it('rejects duplicate team ids on the offending row', () => {
    const result = CreateLeaguePayloadSchema.safeParse({
      name: 'Champions League',
      seed: 1,
      teams: [
        { id: 'a', name: 'Alpha', power: 50 },
        { id: 'a', name: 'Clone', power: 50 },
      ],
    })

    expect(result.success).toBe(false)
    expect(result.error?.issues.some((issue) => issue.path.join('.') === 'teams.1.id')).toBe(true)
  })
})

describe('UpdateMatchPayloadSchema', () => {
  it('accepts non-negative whole-number scores', () => {
    expect(UpdateMatchPayloadSchema.safeParse({ homeGoals: 3, awayGoals: 0 }).success).toBe(true)
  })

  it('rejects negative, fractional, and absurd scores', () => {
    expect(UpdateMatchPayloadSchema.safeParse({ homeGoals: -1, awayGoals: 0 }).success).toBe(false)
    expect(UpdateMatchPayloadSchema.safeParse({ homeGoals: 1.5, awayGoals: 0 }).success).toBe(false)
    expect(UpdateMatchPayloadSchema.safeParse({ homeGoals: 31, awayGoals: 0 }).success).toBe(false)
  })
})

describe('LeagueSnapshotSchema', () => {
  const snapshot = {
    version: 2,
    league: { id: 'L1', name: 'Champions League', seed: 42, currentWeek: 4, totalWeeks: 6 },
    table: [{
      position: 1,
      teamId: 'a',
      name: 'Alpha',
      played: 6,
      won: 5,
      drawn: 1,
      lost: 0,
      goalsFor: 12,
      goalsAgainst: 3,
      goalDifference: 9,
      points: 16,
    }],
    fixtures: [{
      week: 1,
      matches: [{
        id: 'm1',
        homeTeamId: 'a',
        awayTeamId: 'b',
        homeGoals: 2,
        awayGoals: 1,
        played: true,
        origin: 'manual',
      }],
    }],
    predictions: {
      predictor: 'settled-or-simulated',
      odds: [{ teamId: 'a', probability: 0.8 }],
    },
  }

  it('parses a full snapshot, including a null prediction and a null origin', () => {
    expect(LeagueSnapshotSchema.safeParse(snapshot).success).toBe(true)

    const pending = {
      ...snapshot,
      predictions: null,
      fixtures: [{
        week: 1,
        matches: [{ ...snapshot.fixtures[0].matches[0], homeGoals: null, awayGoals: null, played: false, origin: null }],
      }],
    }

    expect(LeagueSnapshotSchema.safeParse(pending).success).toBe(true)
  })

  it('rejects an unknown origin value', () => {
    const drifted = {
      ...snapshot,
      fixtures: [{
        week: 1,
        matches: [{ ...snapshot.fixtures[0].matches[0], origin: 'imported' }],
      }],
    }

    expect(LeagueSnapshotSchema.safeParse(drifted).success).toBe(false)
  })
})
