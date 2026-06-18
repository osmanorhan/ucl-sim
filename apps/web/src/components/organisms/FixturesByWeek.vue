<script setup lang="ts">
import { computed } from 'vue'
import MatchEditor from '../molecules/MatchEditor.vue'
import { weekState } from '../../domain/league'
import { titleCase } from '../../domain/text'
import type { Match, Standing, WeekFixtures } from '../../types/league'
import { weekPresentation } from './weekPresentation'

const props = defineProps<{
  fixtures: WeekFixtures[]
  standings: Standing[]
  disabled: boolean
}>()

const emit = defineEmits<{
  save: [matchId: string, homeGoals: number, awayGoals: number]
}>()

const teamNames = computed(() => new Map(props.standings.map((team) => [team.teamId, team.name])))

function teamName(id: string): string {
  return titleCase(teamNames.value.get(id) ?? id)
}

function save(match: Match, homeGoals: number, awayGoals: number) {
  emit('save', match.id, homeGoals, awayGoals)
}

function weekView(week: WeekFixtures) {
  return weekPresentation[weekState(week)]
}
</script>

<template>
  <section class="fixtures">
    <article
      v-for="week in fixtures"
      :key="week.week"
      class="panel week"
      :class="weekView(week).className"
    >
      <div class="panel-head">
        <h2>Week {{ week.week }}</h2>
        <span class="week-state">{{ weekView(week).label }}</span>
      </div>

      <MatchEditor
        v-for="match in week.matches"
        :key="match.id"
        :match="match"
        :home-name="teamName(match.homeTeamId)"
        :away-name="teamName(match.awayTeamId)"
        :disabled="disabled"
        @save="(_, homeGoals, awayGoals) => save(match, homeGoals, awayGoals)"
      />
    </article>
  </section>
</template>

<style scoped>
.fixtures {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(2, minmax(22rem, 1fr));
}

.week {
  border-left-width: 4px;
}

.week-complete {
  border-left-color: var(--primary);
  background: color-mix(in srgb, var(--primary), #fff 96%);
}

.week-partial {
  border-left-color: var(--simulated);
}

.week-pending {
  border-left-color: var(--border);
  background: color-mix(in srgb, var(--pending), #fff 96%);
}

.week-state {
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
}

@media (max-width: 980px) {
  .fixtures {
    grid-template-columns: 1fr;
  }
}
</style>
