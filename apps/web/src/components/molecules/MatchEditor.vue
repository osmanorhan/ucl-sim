<script setup lang="ts">
import { ref } from 'vue'
import AppButton from '../atoms/AppButton.vue'
import { isManualResult } from '../../domain/league'
import type { Match } from '../../types/league'
import { UpdateMatchPayloadSchema } from '../../validation/leagueSchemas'

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
          max="30"
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
          max="30"
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
