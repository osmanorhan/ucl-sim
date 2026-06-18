<script setup lang="ts">
import { titleCase } from '../../domain/text'
import type { PredictionAvailability, PredictionSet, Standing } from '../../types/league'

defineProps<{
  availability: PredictionAvailability
  predictions: PredictionSet | null
  standings: Standing[]
}>()

function teamName(standings: Standing[], id: string): string {
  return titleCase(standings.find((team) => team.teamId === id)?.name ?? id)
}
</script>

<template>
  <section class="panel predictions">
    <div class="panel-head">
      <h2>Championship odds</h2>
    </div>

    <p v-if="predictions === null" class="muted">
      Available after week {{ availability.availableAfterCompletedWeeks }}.
    </p>

    <div v-else class="odds-list">
      <p class="panel-note">
        Active predictor:
        <span
          class="inline-code"
          :title="`Strategy changes are backend configuration. Current predictor: ${predictions.predictor}.`"
        >
          {{ predictions.predictor }}
        </span>
      </p>
      <div v-for="odd in predictions.odds" :key="odd.teamId" class="odd-row">
        <div>
          <strong>{{ teamName(standings, odd.teamId) }}</strong>
          <span>{{ (odd.probability * 100).toFixed(1) }}%</span>
        </div>
        <progress :value="odd.probability" max="1" />
      </div>
    </div>
  </section>
</template>

<style scoped>
.predictions {
  min-width: 0;
}

.panel-note {
  color: var(--muted);
  font-size: 0.8rem;
}

.inline-code {
  color: var(--text);
  font-weight: 700;
}

.odds-list,
.odd-row {
  display: grid;
  gap: 0.5rem;
}

.odd-row > div {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}
</style>
