import { z } from 'zod'
import { ResultOrigin } from '../domain/league'

export const TeamInputSchema = z.object({
  id: z.string().min(1, 'Team id is required.').max(64, 'Team id must be 64 characters or fewer.'),
  name: z.string().min(1, 'Team name is required.').max(255, 'Team name must be 255 characters or fewer.'),
  power: z.number().positive('Power must be greater than 0.'),
})

export const CreateLeaguePayloadSchema = z.object({
  name: z.string().min(1, 'League name is required.').max(255, 'League name must be 255 characters or fewer.'),
  seed: z.number().int('Seed must be an integer.'),
  teams: z.array(TeamInputSchema).length(4, 'A Champions League group has exactly four teams.'),
}).superRefine((payload, context) => {
  const ids = new Set<string>()
  payload.teams.forEach((team, index) => {
    if (ids.has(team.id)) {
      context.addIssue({
        code: 'custom',
        path: ['teams', index, 'id'],
        message: 'Team ids must be unique.',
      })
    }

    ids.add(team.id)
  })
})

export const UpdateMatchPayloadSchema = z.object({
  homeGoals: z.number()
    .int('Goals must be whole numbers.')
    .min(0, 'Goals cannot be negative.')
    .max(30, 'Even this simulator calls VAR above 30 goals.'),
  awayGoals: z.number()
    .int('Goals must be whole numbers.')
    .min(0, 'Goals cannot be negative.')
    .max(30, 'Even this simulator calls VAR above 30 goals.'),
})

export const LeagueSnapshotSchema = z.object({
  version: z.number().int(),
  league: z.object({
    id: z.string(),
    name: z.string(),
    seed: z.number().int(),
    currentWeek: z.number().int(),
    totalWeeks: z.number().int(),
  }),
  table: z.array(z.object({
    position: z.number().int(),
    teamId: z.string(),
    name: z.string(),
    played: z.number().int(),
    won: z.number().int(),
    drawn: z.number().int(),
    lost: z.number().int(),
    goalsFor: z.number().int(),
    goalsAgainst: z.number().int(),
    goalDifference: z.number().int(),
    points: z.number().int(),
  })),
  fixtures: z.array(z.object({
    week: z.number().int(),
    matches: z.array(z.object({
      id: z.string(),
      homeTeamId: z.string(),
      awayTeamId: z.string(),
      homeGoals: z.number().int().nullable(),
      awayGoals: z.number().int().nullable(),
      played: z.boolean(),
      origin: z.union([z.null(), z.literal(ResultOrigin.Simulated), z.literal(ResultOrigin.Manual)]),
    })),
  })),
  predictions: z.object({
    predictor: z.string(),
    odds: z.array(z.object({
      teamId: z.string(),
      probability: z.number(),
    })),
  }).nullable(),
})

export const EvaluationResultSchema = z.object({
  leagueId: z.string(),
  scorecards: z.array(z.object({
    strategy: z.string(),
    brier: z.number(),
    logLoss: z.number(),
    meanLatencyMs: z.number(),
    deterministic: z.boolean(),
  })),
})
