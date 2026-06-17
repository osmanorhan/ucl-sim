import type { Match, WeekFixtures } from '../types/league'

export const ResultOrigin = {
  Simulated: 'simulated',
  Manual: 'manual',
} as const

export type ResultOriginValue = typeof ResultOrigin[keyof typeof ResultOrigin]

export const WeekState = {
  Complete: 'complete',
  Partial: 'partial',
  Pending: 'pending',
} as const

export type WeekStateValue = typeof WeekState[keyof typeof WeekState]

export const LeagueAction = {
  Create: 'create',
  Load: 'load',
  PlayWeek: 'play-week',
  PlayAll: 'play-all',
  EditResult: 'edit-result',
} as const

export type LeagueActionValue = typeof LeagueAction[keyof typeof LeagueAction]

export function isManualResult(match: Match): boolean {
  return match.origin === ResultOrigin.Manual
}

export function weekState(week: WeekFixtures): WeekStateValue {
  if (week.matches.every((match) => match.played)) {
    return WeekState.Complete
  }

  if (week.matches.some((match) => match.played)) {
    return WeekState.Partial
  }

  return WeekState.Pending
}
