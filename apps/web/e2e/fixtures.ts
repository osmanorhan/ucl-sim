import type { Page } from '@playwright/test'

export const LEAGUE_ID = 'L1'

type Team = { id: string; name: string; power: number }

const TEAMS: Team[] = [
  { id: 'a', name: 'Alpha', power: 90 },
  { id: 'b', name: 'Bravo', power: 65 },
  { id: 'c', name: 'Cosmos', power: 45 },
  { id: 'd', name: 'Delta', power: 30 },
]

const SCHEDULE: ReadonlyArray<ReadonlyArray<readonly [string, string]>> = [
  [['a', 'b'], ['c', 'd']],
  [['a', 'c'], ['d', 'b']],
  [['a', 'd'], ['b', 'c']],
  [['b', 'a'], ['d', 'c']],
  [['c', 'a'], ['b', 'd']],
  [['d', 'a'], ['c', 'b']],
]

type Score = { homeGoals: number; awayGoals: number }
type Edits = Record<string, Score>
type State = { version: number; playedWeeks: number; edits: Edits }

function powerOf(id: string): number {
  const team = TEAMS.find((candidate) => candidate.id === id)
  if (!team) throw new Error(`unknown team ${id}`)
  return team.power
}

function simulated(homeId: string, awayId: string): Score {
  return powerOf(homeId) >= powerOf(awayId)
    ? { homeGoals: 2, awayGoals: 0 }
    : { homeGoals: 0, awayGoals: 2 }
}

function matchId(week: number, slot: number): string {
  return `m${week * 2 + slot - 2}`
}

function standings(fixtures: ReturnType<typeof buildFixtures>) {
  const rows = new Map(TEAMS.map((team) => [team.id, {
    teamId: team.id,
    name: team.name,
    played: 0,
    won: 0,
    drawn: 0,
    lost: 0,
    goalsFor: 0,
    goalsAgainst: 0,
  }]))

  for (const week of fixtures) {
    for (const match of week.matches) {
      if (!match.played || match.homeGoals === null || match.awayGoals === null) continue
      const home = rows.get(match.homeTeamId)!
      const away = rows.get(match.awayTeamId)!
      home.played += 1
      away.played += 1
      home.goalsFor += match.homeGoals
      home.goalsAgainst += match.awayGoals
      away.goalsFor += match.awayGoals
      away.goalsAgainst += match.homeGoals
      if (match.homeGoals > match.awayGoals) { home.won += 1; away.lost += 1 }
      else if (match.homeGoals < match.awayGoals) { away.won += 1; home.lost += 1 }
      else { home.drawn += 1; away.drawn += 1 }
    }
  }

  return [...rows.values()]
    .map((row) => ({
      ...row,
      goalDifference: row.goalsFor - row.goalsAgainst,
      points: row.won * 3 + row.drawn,
    }))
    .sort((left, right) =>
      right.points - left.points
      || right.goalDifference - left.goalDifference
      || right.goalsFor - left.goalsFor
      || left.name.localeCompare(right.name))
    .map((row, index) => ({ position: index + 1, ...row }))
}

function buildFixtures(state: State) {
  return SCHEDULE.map((pairings, weekIndex) => {
    const week = weekIndex + 1
    return {
      week,
      matches: pairings.map(([homeTeamId, awayTeamId], slot) => {
        const id = matchId(week, slot + 1)
        const edit = state.edits[id]
        const played = edit !== undefined || week <= state.playedWeeks
        if (!played) {
          return { id, homeTeamId, awayTeamId, homeGoals: null, awayGoals: null, played: false, origin: null }
        }
        const score = edit ?? simulated(homeTeamId, awayTeamId)
        return {
          id,
          homeTeamId,
          awayTeamId,
          homeGoals: score.homeGoals,
          awayGoals: score.awayGoals,
          played: true,
          origin: edit ? 'manual' : 'simulated',
        }
      }),
    }
  })
}

export function buildSnapshot(state: State) {
  const fixtures = buildFixtures(state)
  const table = standings(fixtures)
  const predictions = state.playedWeeks >= 4
    ? {
        predictor: 'settled-or-simulated',
        odds: table
          .map((row) => ({ teamId: row.teamId, weight: row.points + 1 }))
          .map((row, _, all) => ({
            teamId: row.teamId,
            probability: row.weight / all.reduce((sum, other) => sum + other.weight, 0),
          }))
          .sort((left, right) => right.probability - left.probability),
      }
    : null

  return {
    version: state.version,
    league: { id: LEAGUE_ID, name: 'Champions League', seed: 42, currentWeek: state.playedWeeks, totalWeeks: 6 },
    table,
    fixtures,
    predictions,
  }
}

export function driftedSnapshot() {
  const snapshot = buildSnapshot({ version: 2, playedWeeks: 1, edits: {} })
  ;(snapshot.fixtures[0].matches[0] as { origin: string }).origin = 'imported'
  return snapshot
}

export async function installApi(page: Page): Promise<void> {
  const state: State = { version: 1, playedWeeks: 0, edits: {} }

  await page.route('**/api/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    const method = request.method()
    const json = (body: unknown, status = 200) =>
      route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) })

    if (method === 'OPTIONS') return route.fulfill({ status: 204 })

    if (method === 'POST' && path === '/api/leagues') {
      state.version = 1
      state.playedWeeks = 0
      state.edits = {}
      return json(buildSnapshot(state))
    }
    if (method === 'GET' && path === `/api/leagues/${LEAGUE_ID}`) {
      return json(buildSnapshot(state))
    }
    if (method === 'POST' && path.endsWith('/play-week')) {
      state.playedWeeks = Math.min(6, state.playedWeeks + 1)
      state.version += 1
      return json(buildSnapshot(state))
    }
    if (method === 'POST' && path.endsWith('/play-all')) {
      state.playedWeeks = 6
      state.version += 1
      return json(buildSnapshot(state))
    }
    if (method === 'PUT' && path.startsWith('/api/matches/')) {
      const id = path.split('/').pop()!
      const body = request.postDataJSON() as Score
      state.edits[id] = { homeGoals: body.homeGoals, awayGoals: body.awayGoals }
      state.version += 1
      return json(buildSnapshot(state))
    }
    if (method === 'GET' && path.endsWith('/evaluation')) {
      return json({
        leagueId: LEAGUE_ID,
        scorecards: [
          { strategy: 'monte-carlo', brier: 0.18, logLoss: 0.42, meanLatencyMs: 2.4, deterministic: false },
          { strategy: 'points-heuristic', brier: 0.27, logLoss: 0.55, meanLatencyMs: 0.3, deterministic: true },
        ],
      })
    }

    return json({ message: `unhandled ${method} ${path}` }, 500)
  })
}
