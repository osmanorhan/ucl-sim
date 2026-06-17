<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppLayout from '../components/layout/AppLayout.vue'
import LeagueCreateForm from '../components/molecules/LeagueCreateForm.vue'
import { LeagueAction } from '../domain/league'
import { useCreateLeagueViewModel } from '../viewModels/createLeagueViewModel'

const { pending, pendingAction, error, create } = useCreateLeagueViewModel(useRouter())
</script>

<template>
  <AppLayout title="Champions League" subtitle="Create league" :error="error">
    <LeagueCreateForm
      :disabled="pending"
      :submitting="pendingAction === LeagueAction.Create"
      @create="create"
    />
  </AppLayout>
</template>
