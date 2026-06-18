<script setup lang="ts">
import { ref } from 'vue'
import { z } from 'zod'
import AppButton from '../atoms/AppButton.vue'
import PowerSlider from '../atoms/PowerSlider.vue'
import TextField from '../atoms/TextField.vue'
import type { CreateLeaguePayload, TeamInput } from '../../types/league'
import { CreateLeaguePayloadSchema } from '../../validation/leagueSchemas'
import { sampleClubs } from '../../data/europeanClubs'

defineProps<{
  disabled: boolean
  submitting: boolean
}>()

const emit = defineEmits<{
  create: [payload: CreateLeaguePayload]
}>()

const name = ref('Champions League')
const teams = ref<TeamInput[]>(
  sampleClubs(4).map((club, i) => ({ id: String(i), name: club.name, power: club.power })),
)
const errors = ref<Record<string, string>>({})

function submit() {
  const payload = {
    name: name.value,
    seed: Math.floor(Math.random() * 1_000_000),
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
      </div>

      <TextField id="league-name" v-model="name" label="Name" :error="errors.name" class="name-field" />
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
          <PowerSlider
            :id="`team-${team.id}-power`"
            v-model="team.power"
            :error="errors[`teams.${index}.power`]"
          />
        </div>
      </div>

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

<style scoped>
.create-form {
  display: grid;
  gap: 1rem;
}

.create-section {
  display: grid;
  gap: 0.75rem;
}

.section-head {
  display: grid;
  gap: 0.2rem;
}

.section-head p {
  color: var(--muted);
  font-size: 0.86rem;
}

.create-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-top: 1px solid var(--border);
  padding-top: 0.75rem;
  color: var(--muted);
  font-size: 0.86rem;
}

.name-field {
  max-width: 760px;
}

.team-grid {
  display: grid;
  gap: 0.75rem;
  max-width: 760px;
}

.team-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 14rem;
  gap: 0.5rem;
  align-items: start;
}

.field-error {
  min-height: 1rem;
  color: var(--danger);
  font-size: 0.72rem;
  line-height: 1;
}

@media (max-width: 980px) {
  .create-form,
  .team-row {
    grid-template-columns: 1fr;
  }

  .create-footer {
    display: grid;
  }
}
</style>
