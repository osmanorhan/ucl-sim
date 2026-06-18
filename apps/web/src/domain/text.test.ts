import { describe, expect, it } from 'vitest'
import { titleCase } from './text'

describe('titleCase', () => {
  it('capitalises the first letter of every word', () => {
    expect(titleCase('champions league')).toBe('Champions League')
  })

  it('lowercases the rest, so shouting becomes title case', () => {
    expect(titleCase('BAYERN MÜNCHEN')).toBe('Bayern München')
  })

  it('preserves accents, multi-script letters and in-word punctuation', () => {
    expect(titleCase('beşiktaş')).toBe('Beşiktaş')
    expect(titleCase("borussia m'gladbach")).toBe("Borussia M'gladbach")
  })

  it('leaves an empty string untouched', () => {
    expect(titleCase('')).toBe('')
  })
})
