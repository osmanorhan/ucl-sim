<script setup lang="ts">
import type { Standing } from '../../types/league'

defineProps<{ standings: Standing[] }>()
</script>

<template>
  <section class="panel table-panel">
    <div class="panel-head">
      <h2>Standings</h2>
    </div>

    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th class="team-heading">Club</th>
          <th><abbr title="Played">P</abbr></th>
          <th><abbr title="Wins">W</abbr></th>
          <th><abbr title="Draws">D</abbr></th>
          <th><abbr title="Losses">L</abbr></th>
          <th><abbr title="Goals for">GF</abbr></th>
          <th><abbr title="Goals against">GA</abbr></th>
          <th><abbr title="Goal difference">GD</abbr></th>
          <th><abbr title="Points">Pts</abbr></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="standing in standings" :key="standing.teamId">
          <td class="rank-cell">{{ standing.position }}</td>
          <td class="team-cell">{{ standing.name }}</td>
          <td>{{ standing.played }}</td>
          <td>{{ standing.won }}</td>
          <td>{{ standing.drawn }}</td>
          <td>{{ standing.lost }}</td>
          <td>{{ standing.goalsFor }}</td>
          <td>{{ standing.goalsAgainst }}</td>
          <td>{{ standing.goalDifference }}</td>
          <td><strong>{{ standing.points }}</strong></td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
