import { storeToRefs } from 'pinia'
import type { Router } from 'vue-router'
import { simulationRoute } from '../router'
import { useLeagueStore } from '../stores/league'
import type { CreateLeaguePayload } from '../types/league'

export function useCreateLeagueViewModel(router: Router) {
  const store = useLeagueStore()
  const { pending, pendingAction, error } = storeToRefs(store)

  async function create(payload: CreateLeaguePayload) {
    const snapshot = await store.create(payload)

    if (snapshot !== null) {
      await router.push(simulationRoute(snapshot.league.id))
    }
  }

  return { pending, pendingAction, error, create }
}
