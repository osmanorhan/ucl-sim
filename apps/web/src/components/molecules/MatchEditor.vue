<script setup lang="ts">
import { ref } from 'vue'
import AppButton from '../atoms/AppButton.vue'
import { isManualResult } from '../../domain/league'
import type { Match } from '../../types/league'
import { MAX_GOALS, UpdateMatchPayloadSchema } from '../../validation/leagueSchemas'

const props = defineProps<{
  match: Match
  homeName: string
  awayName: string
  disabled: boolean
}>()

const emit = defineEmits<{
  save: [matchId: string, homeGoals: number, awayGoals: number]
}>()

const error = ref<string | null>(null)

function save(event: Event) {
  const form = new FormData(event.currentTarget as HTMLFormElement)
  const result = UpdateMatchPayloadSchema.safeParse({
    homeGoals: Number(form.get('homeGoals')),
    awayGoals: Number(form.get('awayGoals')),
  })

  if (!result.success) {
    error.value = result.error.issues[0]?.message ?? 'Invalid result.'

    return
  }

  error.value = null
  emit('save', props.match.id, result.data.homeGoals, result.data.awayGoals)
}
</script>

<template>
  <article class="match">
    <form v-if="match.played" class="match-line" @submit.prevent="save">
      <span
        v-if="isManualResult(match)"
        class="manual-dot"
        title="This result was manually updated."
        aria-label="Manually updated result"
      />
      <span v-else class="manual-dot-spacer" />

      <span class="team-name home">{{ homeName }}</span>

      <label class="score-field" :for="`${match.id}-home`">
        <span class="sr-only">{{ homeName }} goals</span>
        <input
          :id="`${match.id}-home`"
          class="score-input"
          name="homeGoals"
          type="number"
          min="0"
          :max="MAX_GOALS"
          :title="`${homeName} goals`"
          :value="match.homeGoals ?? 0"
        >
      </label>

      <span class="score-separator">-</span>

      <label class="score-field" :for="`${match.id}-away`">
        <span class="sr-only">{{ awayName }} goals</span>
        <input
          :id="`${match.id}-away`"
          class="score-input"
          name="awayGoals"
          type="number"
          min="0"
          :max="MAX_GOALS"
          :title="`${awayName} goals`"
          :value="match.awayGoals ?? 0"
        >
      </label>

      <span class="team-name away">{{ awayName }}</span>

      <AppButton type="submit" :disabled="disabled">Update</AppButton>
    </form>

    <div v-else class="match-line scheduled">
      <span class="manual-dot-spacer" />
      <span class="team-name home">{{ homeName }}</span>
      <span class="score-separator">vs</span>
      <span class="team-name away">{{ awayName }}</span>
      <span class="badge pending">Scheduled</span>
    </div>

    <span class="match-error">{{ error }}</span>
  </article>
</template>

<style scoped>
.match {
  display: grid;
  gap: 0.15rem;
  border-top: 1px solid var(--border);
  padding: 0.7rem 0;
}

.match:first-of-type {
  border-top: 0;
  padding-top: 0.15rem;
}

.match-line {
  display: grid;
  grid-template-columns: 0.75rem minmax(5rem, 1fr) 3rem auto 3rem minmax(5rem, 1fr) 5.5rem;
  gap: 0.45rem;
  align-items: center;
}

.match-line.scheduled {
  grid-template-columns: 0.75rem minmax(5rem, 1fr) 3rem minmax(5rem, 1fr) 5.5rem;
  color: var(--muted);
}

.team-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.team-name.home {
  text-align: right;
}

.team-name.away {
  text-align: left;
}

.score-separator {
  text-align: center;
  font-weight: 800;
}

.score-field {
  display: block;
}

.score-input {
  min-height: 2rem;
  padding: 0.3rem 0.25rem;
  text-align: center;
  overflow: hidden;
  text-overflow: ellipsis;
}

.badge {
  display: inline-flex;
  justify-content: center;
  width: max-content;
  max-width: 100%;
  border-radius: 999px;
  padding: 0.15rem 0.5rem;
  color: #fff;
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.25;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge.pending {
  background: var(--pending);
}

.manual-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  background: var(--manual);
}

.manual-dot-spacer {
  width: 0.55rem;
  height: 0.55rem;
}

.match-error {
  grid-column: 1 / -1;
  min-height: 0.9rem;
  color: var(--danger);
  font-size: 0.72rem;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

@media (max-width: 980px) {
  .match {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .match-line {
    grid-template-columns: 0.75rem minmax(0, 1fr) 3rem auto 3rem minmax(0, 1fr);
  }

  .match-line :deep(.button) {
    grid-column: 2 / -1;
  }
}
</style>
