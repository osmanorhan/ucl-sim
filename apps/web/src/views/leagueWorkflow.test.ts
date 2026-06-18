import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createRouter, createWebHistory, type Router } from 'vue-router'
import {
  createLeague,
  getEvaluation,
  getLeague,
  playWeek,
  updateMatch,
} from '../api/client'
import { ResultOrigin } from '../domain/league'
import { createLeagueRoute, simulationRoute } from '../router'
import type { EvaluationResult, LeagueSnapshot } from '../types/league'
import CreateLeagueView from './CreateLeagueView.vue'
import SimulationView from './SimulationView.vue'

vi.mock('../api/client', () => ({
  createLeague: vi.fn(),
  getEvaluation: vi.fn(),
  getLeague: vi.fn(),
  playAll: vi.fn(),
  playWeek: vi.fn(),
  updateMatch: vi.fn(),
}))

function snapshot(overrides: Partial<LeagueSnapshot> = {}): LeagueSnapshot {
  return {
    version: 1,
    league: { id: 'L1', name: 'Champions League', seed: 42, currentWeek: 0, totalWeeks: 6 },
    table: [
      {
        position: 1,
        teamId: 'alpha',
        name: 'Alpha',
        played: 0,
        won: 0,
        drawn: 0,
        lost: 0,
        goalsFor: 0,
        goalsAgainst: 0,
        goalDifference: 0,
        points: 0,
      },
      {
        position: 2,
        teamId: 'beta',
        name: 'Beta',
        played: 0,
        won: 0,
        drawn: 0,
        lost: 0,
        goalsFor: 0,
        goalsAgainst: 0,
        goalDifference: 0,
        points: 0,
      },
    ],
    fixtures: [{
      week: 1,
      matches: [{
        id: 'm1',
        homeTeamId: 'alpha',
        awayTeamId: 'beta',
        homeGoals: null,
        awayGoals: null,
        played: false,
        origin: null,
      }],
    }],
    predictionAvailability: { available: false, availableAfterCompletedWeeks: 4 },
    predictions: null,
    ...overrides,
  }
}

async function routerAt(path: string): Promise<Router> {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: createLeagueRoute, component: CreateLeagueView },
      { path: '/leagues/:leagueId/simulation', component: SimulationView, props: true },
    ],
  })

  await router.push(path)
  await router.isReady()

  return router
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('league workflow', () => {
  it('creates a league and opens the simulation workspace', async () => {
    const created = snapshot()
    vi.mocked(createLeague).mockResolvedValueOnce(created)
    const router = await routerAt(createLeagueRoute)

    const wrapper = mount(CreateLeagueView, {
      global: { plugins: [router] },
    })

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(createLeague).toHaveBeenCalledWith(expect.objectContaining({
      name: 'Champions League',
      teams: expect.arrayContaining([
        expect.objectContaining({ name: expect.any(String), power: expect.any(Number) }),
      ]),
    }))
    expect(router.currentRoute.value.fullPath).toBe(simulationRoute('L1'))
  })

  it('plays a week, allows a manual correction, and benchmarks predictors for the visible league', async () => {
    const initial = snapshot()
    const played = snapshot({
      version: 2,
      league: { ...initial.league, currentWeek: 1 },
      fixtures: [{
        week: 1,
        matches: [{
          ...initial.fixtures[0].matches[0],
          homeGoals: 2,
          awayGoals: 0,
          played: true,
          origin: ResultOrigin.Simulated,
        }],
      }],
    })
    const corrected = snapshot({
      ...played,
      version: 3,
      fixtures: [{
        week: 1,
        matches: [{
          ...played.fixtures[0].matches[0],
          homeGoals: 3,
          awayGoals: 1,
          origin: ResultOrigin.Manual,
        }],
      }],
      predictions: {
        predictor: 'settled-or-simulated',
        odds: [{ teamId: 'alpha', probability: 0.72 }],
      },
      predictionAvailability: { available: true, availableAfterCompletedWeeks: 4 },
    })
    const evaluation: EvaluationResult = {
      leagueId: 'L1',
      scorecards: [
        { strategy: 'monte-carlo', brier: 0.12, logLoss: 0.34, meanLatencyMs: 4.6, deterministic: false },
      ],
    }

    vi.mocked(getLeague).mockResolvedValueOnce(initial)
    vi.mocked(playWeek).mockResolvedValueOnce(played)
    vi.mocked(updateMatch).mockResolvedValueOnce(corrected)
    vi.mocked(getEvaluation).mockResolvedValueOnce(evaluation)

    const router = await routerAt(simulationRoute('L1'))
    const wrapper = mount(SimulationView, {
      props: { leagueId: 'L1' },
      global: { plugins: [router] },
    })

    await flushPromises()
    expect(getLeague).toHaveBeenCalledWith('L1')
    expect(wrapper.text()).toContain('Week 0 of 6')
    expect(wrapper.text()).toContain('Alpha')

    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(playWeek).toHaveBeenCalledWith('L1')
    expect(wrapper.text()).toContain('Week 1 of 6')

    await wrapper.get('input[title="Alpha goals"]').setValue('3')
    await wrapper.get('input[title="Beta goals"]').setValue('1')
    await wrapper.get('form.match-line').trigger('submit')
    await flushPromises()
    expect(updateMatch).toHaveBeenCalledWith('m1', 3, 1)
    expect(wrapper.text()).toContain('72.0%')

    await wrapper.findAll('button').find((button) => button.text() === 'Run')?.trigger('click')
    await flushPromises()
    expect(getEvaluation).toHaveBeenCalledWith('L1')
    expect(wrapper.text()).toContain('monte-carlo')
    expect(wrapper.text()).toContain('0.1200')
  })
})
