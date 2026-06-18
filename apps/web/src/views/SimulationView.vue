<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/atoms/AppButton.vue'
import AppLayout from '../components/layout/AppLayout.vue'
import EvaluationScorecard from '../components/organisms/EvaluationScorecard.vue'
import FixturesByWeek from '../components/organisms/FixturesByWeek.vue'
import LeagueTable from '../components/organisms/LeagueTable.vue'
import PredictionPanel from '../components/organisms/PredictionPanel.vue'
import { LeagueAction } from '../domain/league'
import { useSimulationViewModel } from '../viewModels/simulationViewModel'

const props = defineProps<{ leagueId: string }>()
const {
  snapshot,
  evaluation,
  pending,
  pendingAction,
  evaluating,
  error,
  enter,
  createNewLeague,
  playNextWeek,
  playSeason,
  editResult,
  evaluate,
} = useSimulationViewModel(props.leagueId, useRouter())

onMounted(() => {
  void enter()
})
</script>

<template>
  <AppLayout
    :title="snapshot?.league.name ?? 'Champions League'"
    :subtitle="snapshot ? `Week ${snapshot.league.currentWeek} of ${snapshot.league.totalWeeks} · Seed ${snapshot.league.seed}` : 'Loading league'"
    :error="error"
  >
    <template #actions>
      <template v-if="snapshot">
        <AppButton
          variant="primary"
          :disabled="pending || snapshot.league.currentWeek >= snapshot.league.totalWeeks"
          @click="playNextWeek"
        >
          {{ pendingAction === LeagueAction.PlayWeek ? 'Playing week' : 'Play week' }}
        </AppButton>
        <AppButton
          :disabled="pending || snapshot.league.currentWeek >= snapshot.league.totalWeeks"
          @click="playSeason"
        >
          {{ pendingAction === LeagueAction.PlayAll ? 'Playing season' : 'Play all' }}
        </AppButton>
      </template>
      <button class="text-button" type="button" @click="createNewLeague">New league</button>
    </template>
    <template v-if="snapshot">
      <div class="layout">
        <div class="main-column">
          <LeagueTable :standings="snapshot.table" />
          <FixturesByWeek
            :fixtures="snapshot.fixtures"
            :standings="snapshot.table"
            :disabled="pending"
            @save="editResult"
          />
        </div>

        <aside class="side-column">
          <PredictionPanel
            :availability="snapshot.predictionAvailability"
            :predictions="snapshot.predictions"
            :standings="snapshot.table"
          />
          <EvaluationScorecard
            :scorecards="evaluation"
            :disabled="pending"
            :evaluating="evaluating"
            @evaluate="evaluate"
          />
        </aside>
      </div>
    </template>
  </AppLayout>
</template>

<style scoped>
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 420px;
  gap: 0.75rem;
  align-items: start;
}

.main-column,
.side-column {
  display: grid;
  gap: 0.75rem;
}

.side-column {
  min-width: 0;
}

.text-button {
  border: 0;
  padding: 0;
  background: transparent;
  color: var(--primary);
  font-weight: 700;
}

@media (max-width: 1180px) {
  .layout {
    grid-template-columns: 1fr;
  }

  .side-column {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 980px) {
  .side-column {
    order: -1;
  }
}

@media (max-width: 760px) {
  .side-column {
    grid-template-columns: 1fr;
  }
}
</style>
