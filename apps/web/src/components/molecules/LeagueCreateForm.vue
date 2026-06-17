<script setup lang="ts">
import { ref } from 'vue'
import { z } from 'zod'
import AppButton from '../atoms/AppButton.vue'
import NumberField from '../atoms/NumberField.vue'
import TextField from '../atoms/TextField.vue'
import type { CreateLeaguePayload, TeamInput } from '../../types/league'
import { CreateLeaguePayloadSchema } from '../../validation/leagueSchemas'

defineProps<{
  disabled: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  create: [payload: CreateLeaguePayload]
}>()

const name = ref('Champions League')
const seed = ref(42)
const teams = ref<TeamInput[]>([
  { id: 'a', name: 'Alpha', power: 90 },
  { id: 'b', name: 'Bravo', power: 65 },
  { id: 'c', name: 'Cosmos', power: 45 },
  { id: 'd', name: 'Delta', power: 30 },
])
const errors = ref<Record<string, string>>({})
const teamSeq = ref(teams.value.length)

function addTeam() {
  teamSeq.value += 1
  teams.value.push({ id: `t${teamSeq.value}`, name: '', power: 50 })
}

function removeTeam(index: number) {
  teams.value.splice(index, 1)
}

function submit() {
  const payload = {
    name: name.value,
    seed: seed.value,
    teams: teams.value.map((team) => ({ ...team })),
  }

  const result = CreateLeaguePayloadSchema.safeParse(payload)

  if (!result.success) {
    errors.value = fieldErrors(result.error)

    return
  }

  errors.value = {}
  emit('create', result.data)
}

function fieldErrors(error: z.ZodError): Record<string, string> {
  const mapped: Record<string, string> = {}

  for (const issue of error.issues) {
    const key = issue.path.join('.')
    mapped[key] ??= issue.message
  }

  return mapped
}
</script>

<template>
  <form class="panel create-form" @submit.prevent="submit">
    <section class="create-section">
      <div class="section-head">
        <h2>League setup</h2>
        <p>Seed keeps the run reproducible.</p>
      </div>

      <div class="form-grid">
        <TextField id="league-name" v-model="name" label="Name" :error="errors.name" />
        <NumberField id="league-seed" v-model="seed" label="Seed" :error="errors.seed" />
      </div>
    </section>

    <section class="create-section">
      <div class="section-head">
        <h2>Teams</h2>
        <p>Power drives the simulator; higher is stronger.</p>
      </div>

      <div class="team-grid">
        <div v-for="(team, index) in teams" :key="team.id" class="team-row">
          <TextField
            :id="`team-${team.id}-name`"
            v-model="team.name"
            label="Name"
            :error="errors[`teams.${index}.name`]"
          />
          <NumberField
            :id="`team-${team.id}-power`"
            v-model="team.power"
            label="Power"
            :min="1"
            :error="errors[`teams.${index}.power`]"
          />
          <button
            type="button"
            class="text-button remove-team"
            :disabled="teams.length <= 2"
            :aria-label="`Remove ${team.name || 'team'}`"
            @click="removeTeam(index)"
          >
            Remove
          </button>
        </div>
      </div>

      <AppButton type="button" @click="addTeam">Add team</AppButton>
      <p v-if="errors.teams" class="field-error">{{ errors.teams }}</p>
    </section>

    <footer class="create-footer">
      <p class="muted">Creates fixtures and opens the simulation workspace.</p>
      <AppButton type="submit" variant="primary" :disabled="disabled">
        {{ submitting ? 'Creating league' : 'Create league' }}
      </AppButton>
    </footer>
  </form>
</template>
