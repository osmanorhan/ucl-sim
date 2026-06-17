import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import type { Router } from 'vue-router'
import { createLeagueRoute } from '../router'
import { useLeagueStore } from '../stores/league'

export function useSimulationViewModel(leagueId: string, router: Router) {
  const store = useLeagueStore()
  const { snapshot, evaluation, pending, pendingAction, evaluating, error } = storeToRefs(store)

  async function enter() {
    if (snapshot.value?.league.id !== leagueId) {
      await store.load(leagueId)
    }
  }

  function createNewLeague() {
    store.clear()
    void router.push(createLeagueRoute)
  }

  return {
    snapshot: computed(() => snapshot.value?.league.id === leagueId ? snapshot.value : null),
    evaluation,
    pending,
    pendingAction,
    evaluating,
    error,
    enter,
    createNewLeague,
    playNextWeek: store.playNextWeek,
    playSeason: store.playSeason,
    editResult: store.editResult,
    evaluate: store.evaluate,
  }
}
