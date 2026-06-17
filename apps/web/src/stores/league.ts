import { defineStore } from 'pinia'
import {
  createLeague,
  getEvaluation,
  getLeague,
  playAll,
  playWeek,
  updateMatch,
} from '../api/client'
import { LeagueAction, type LeagueActionValue } from '../domain/league'
import type {
  CreateLeaguePayload,
  EvaluationScorecard,
  LeagueSnapshot,
} from '../types/league'

type LeagueState = {
  snapshot: LeagueSnapshot | null
  evaluation: EvaluationScorecard[]
  pending: boolean
  pendingAction: null | LeagueActionValue
  evaluating: boolean
  error: string | null
}

export const useLeagueStore = defineStore('league', {
  state: (): LeagueState => ({
    snapshot: null,
    evaluation: [],
    pending: false,
    pendingAction: null,
    evaluating: false,
    error: null,
  }),

  actions: {
    async create(payload: CreateLeaguePayload): Promise<LeagueSnapshot | null> {
      let created: LeagueSnapshot | null = null

      await this.withPending(LeagueAction.Create, async () => {
        created = await createLeague(payload)
        this.applySnapshot(created)
        this.evaluation = []
      })

      return created
    },

    async load(id: string) {
      await this.withPending(LeagueAction.Load, async () => {
        this.applySnapshot(await getLeague(id))
        this.evaluation = []
      })
    },

    async playNextWeek() {
      await this.withPending(LeagueAction.PlayWeek, async () => {
        this.applySnapshot(await playWeek(this.requireLeagueId()))
        this.evaluation = []
      })
    },

    async playSeason() {
      await this.withPending(LeagueAction.PlayAll, async () => {
        this.applySnapshot(await playAll(this.requireLeagueId()))
        this.evaluation = []
      })
    },

    async editResult(matchId: string, homeGoals: number, awayGoals: number) {
      await this.withPending(LeagueAction.EditResult, async () => {
        this.applySnapshot(await updateMatch(matchId, homeGoals, awayGoals))
        this.evaluation = []
      })
    },

    async evaluate() {
      this.evaluating = true
      this.error = null

      try {
        this.evaluation = (await getEvaluation(this.requireLeagueId())).scorecards
      } catch (error) {
        this.error = error instanceof Error ? error.message : 'Evaluation failed.'
      } finally {
        this.evaluating = false
      }
    },

    applySnapshot(snapshot: LeagueSnapshot) {
      if (
        this.snapshot !== null
        && snapshot.league.id === this.snapshot.league.id
        && snapshot.version < this.snapshot.version
      ) {
        return
      }

      this.snapshot = snapshot
    },

    clear() {
      this.snapshot = null
      this.evaluation = []
      this.error = null
      this.pendingAction = null
    },

    requireLeagueId(): string {
      if (this.snapshot === null) {
        throw new Error('Create a league first.')
      }

      return this.snapshot.league.id
    },

    async withPending(action: LeagueState['pendingAction'], work: () => Promise<void>) {
      this.pending = true
      this.pendingAction = action
      this.error = null

      try {
        await work()
      } catch (error) {
        this.error = error instanceof Error ? error.message : 'Request failed.'
      } finally {
        this.pending = false
        this.pendingAction = null
      }
    },
  },
})
