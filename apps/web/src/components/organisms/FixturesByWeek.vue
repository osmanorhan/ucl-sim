<script setup lang="ts">
import { computed } from 'vue'
import MatchEditor from '../molecules/MatchEditor.vue'
import { weekState } from '../../domain/league'
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
  return teamNames.value.get(id) ?? id
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
