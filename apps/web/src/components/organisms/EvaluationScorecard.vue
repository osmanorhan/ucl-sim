<script setup lang="ts">
import AppButton from '../atoms/AppButton.vue'
import type { EvaluationScorecard } from '../../types/league'

defineProps<{
  scorecards: EvaluationScorecard[]
  disabled: boolean
  evaluating: boolean
}>()

const emit = defineEmits<{
  evaluate: []
}>()
</script>

<template>
  <section class="panel scorecard">
    <div class="panel-head">
      <div>
        <h2>Predictor benchmark</h2>
        <p class="panel-note">Compares registered predictors for this league state.</p>
      </div>
      <AppButton :disabled="disabled || evaluating" @click="emit('evaluate')">
        {{ evaluating ? 'Running' : 'Run' }}
      </AppButton>
    </div>

    <p v-if="scorecards.length === 0" class="muted">Live strategy is configured by the API.</p>

    <div v-if="evaluating" class="benchmark-loading" aria-live="polite">
      <span class="spinner" />
      <span>Running benchmark</span>
    </div>

    <div v-else-if="scorecards.length > 0" class="scorecard-table">
      <table>
        <thead>
          <tr>
            <th>Strategy</th>
            <th>Brier</th>
            <th>Log loss</th>
            <th>Latency</th>
            <th>Det.</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="card in scorecards" :key="card.strategy">
            <td class="team-cell">{{ card.strategy }}</td>
            <td>{{ card.brier.toFixed(4) }}</td>
            <td>{{ card.logLoss.toFixed(4) }}</td>
            <td>{{ card.meanLatencyMs.toFixed(1) }}ms</td>
            <td>{{ card.deterministic ? 'yes' : 'no' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
