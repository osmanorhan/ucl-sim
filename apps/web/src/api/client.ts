import type {
  CreateLeaguePayload,
  EvaluationResult,
  LeagueSnapshot,
} from '../types/league'
import {
  CreateLeaguePayloadSchema,
  EvaluationResultSchema,
  LeagueSnapshotSchema,
  UpdateMatchPayloadSchema,
} from '../validation/leagueSchemas'

const baseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://127.0.0.1:8000'

type HttpMethod = 'GET' | 'POST' | 'PUT'

type RequestOptions = {
  method?: HttpMethod
  body?: unknown
}

export async function createLeague(payload: CreateLeaguePayload): Promise<LeagueSnapshot> {
  return LeagueSnapshotSchema.parse(await request('/api/leagues', {
    method: 'POST',
    body: CreateLeaguePayloadSchema.parse(payload),
  }))
}

export async function getLeague(id: string): Promise<LeagueSnapshot> {
  return LeagueSnapshotSchema.parse(await request(`/api/leagues/${id}`))
}

export async function playWeek(id: string): Promise<LeagueSnapshot> {
  return LeagueSnapshotSchema.parse(await request(`/api/leagues/${id}/play-week`, { method: 'POST' }))
}

export async function playAll(id: string): Promise<LeagueSnapshot> {
  return LeagueSnapshotSchema.parse(await request(`/api/leagues/${id}/play-all`, { method: 'POST' }))
}

export async function updateMatch(
  id: string,
  homeGoals: number,
  awayGoals: number,
): Promise<LeagueSnapshot> {
  return LeagueSnapshotSchema.parse(await request(`/api/matches/${id}`, {
    method: 'PUT',
    body: UpdateMatchPayloadSchema.parse({ homeGoals, awayGoals }),
  }))
}

export async function getEvaluation(id: string): Promise<EvaluationResult> {
  return EvaluationResultSchema.parse(await request(`/api/leagues/${id}/evaluation`))
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(`${baseUrl}${path}`, {
    method: options.method ?? 'GET',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok) {
    throw new Error(errorMessage(payload, response.statusText))
  }

  return payload as T
}

function errorMessage(payload: unknown, fallback: string): string {
  if (isObject(payload) && typeof payload.message === 'string') {
    return payload.message
  }

  if (isObject(payload) && isObject(payload.errors)) {
    return Object.values(payload.errors).flat().join(' ')
  }

  return fallback
}

function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}
