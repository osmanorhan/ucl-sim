import type { ResultOriginValue } from '../domain/league'

export type ResultOrigin = null | ResultOriginValue

export type LeagueMeta = {
  id: string
  name: string
  seed: number
  currentWeek: number
  totalWeeks: number
}

export type Standing = {
  position: number
  teamId: string
  name: string
  played: number
  won: number
  drawn: number
  lost: number
  goalsFor: number
  goalsAgainst: number
  goalDifference: number
  points: number
}

export type Match = {
  id: string
  homeTeamId: string
  awayTeamId: string
  homeGoals: number | null
  awayGoals: number | null
  played: boolean
  origin: ResultOrigin
}

export type WeekFixtures = {
  week: number
  matches: Match[]
}

export type Prediction = {
  teamId: string
  probability: number
}

export type PredictionSet = {
  predictor: string
  odds: Prediction[]
}

export type PredictionAvailability = {
  available: boolean
  availableAfterCompletedWeeks: number
}

export type LeagueSnapshot = {
  version: number
  league: LeagueMeta
  table: Standing[]
  fixtures: WeekFixtures[]
  predictionAvailability: PredictionAvailability
  predictions: PredictionSet | null
}

export type TeamInput = {
  id: string
  name: string
  power: number
}

export type CreateLeaguePayload = {
  name: string
  seed: number
  teams: TeamInput[]
}

export type EvaluationScorecard = {
  strategy: string
  brier: number
  logLoss: number
  meanLatencyMs: number
  deterministic: boolean
}

export type EvaluationResult = {
  leagueId: string
  scorecards: EvaluationScorecard[]
}
