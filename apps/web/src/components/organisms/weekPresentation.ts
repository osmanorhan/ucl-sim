import { WeekState, type WeekStateValue } from '../../domain/league'

export type WeekPresentation = {
  label: string
  className: string
}

export const weekPresentation: Record<WeekStateValue, WeekPresentation> = {
  [WeekState.Complete]: { label: '✓ Completed', className: 'week-complete' },
  [WeekState.Partial]: { label: 'In progress', className: 'week-partial' },
  [WeekState.Pending]: { label: 'Pending', className: 'week-pending' },
}
