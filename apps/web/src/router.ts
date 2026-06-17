import { createRouter, createWebHistory } from 'vue-router'
import CreateLeagueView from './views/CreateLeagueView.vue'
import NotFoundView from './views/NotFoundView.vue'
import SimulationView from './views/SimulationView.vue'

export const createLeagueRoute = '/leagues/create'

export function simulationRoute(leagueId: string): string {
  return `/leagues/${leagueId}/simulation`
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: createLeagueRoute },
    { path: createLeagueRoute, component: CreateLeagueView },
    {
      path: '/leagues/:leagueId/simulation',
      component: SimulationView,
      props: true,
    },
    { path: '/:pathMatch(.*)*', component: NotFoundView },
  ],
})
