const WORD_START = /(^|\s)(\p{L})/gu

export function titleCase(value: string): string {
  return value
    .toLocaleLowerCase()
    .replace(WORD_START, (_, lead: string, first: string) => lead + first.toLocaleUpperCase())
}
