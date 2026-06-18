export type ClubEntry = { name: string; power: number }

export const EUROPEAN_CLUBS: ClubEntry[] = [
  { name: 'Real Madrid',        power: 95 },
  { name: 'Manchester City',    power: 93 },
  { name: 'Bayern Munich',      power: 91 },
  { name: 'Liverpool',          power: 89 },
  { name: 'Barcelona',          power: 88 },
  { name: 'PSG',                power: 87 },
  { name: 'Arsenal',            power: 85 },
  { name: 'Inter Milan',        power: 84 },
  { name: 'Atlético Madrid',    power: 84 },
  { name: 'Borussia Dortmund',  power: 82 },
  { name: 'Chelsea',            power: 82 },
  { name: 'AC Milan',           power: 81 },
  { name: 'Juventus',           power: 80 },
  { name: 'Bayer Leverkusen',   power: 82 },
  { name: 'Atalanta',           power: 79 },
  { name: 'Napoli',             power: 79 },
  { name: 'Benfica',            power: 77 },
  { name: 'Aston Villa',        power: 77 },
  { name: 'Newcastle United',   power: 76 },
  { name: 'Porto',              power: 76 },
  { name: 'Roma',               power: 75 },
  { name: 'PSV Eindhoven',      power: 75 },
  { name: 'Feyenoord',          power: 74 },
  { name: 'Marseille',          power: 73 },
  { name: 'Monaco',             power: 72 },
  { name: 'Ajax',               power: 72 },
  { name: 'Sevilla',            power: 71 },
  { name: 'Villarreal',         power: 70 },
]

export function sampleClubs(n: number): ClubEntry[] {
  const pool = [...EUROPEAN_CLUBS]
  const result: ClubEntry[] = []
  for (let i = 0; i < n && pool.length > 0; i++) {
    const idx = Math.floor(Math.random() * pool.length)
    result.push(pool.splice(idx, 1)[0])
  }
  return result
}
