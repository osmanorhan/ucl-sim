<script setup lang="ts">
import { useRouter } from 'vue-router'
import AppButton from '../components/atoms/AppButton.vue'
import { createLeagueRoute } from '../router'

const router = useRouter()
</script>

<template>
  <main class="app-shell flow-shell">
    <section class="panel">
      <div class="panel-head">
        <h1>Not found</h1>
      </div>
      <AppButton variant="primary" type="button" @click="router.push(createLeagueRoute)">Create league</AppButton>
    </section>
  </main>
</template>
