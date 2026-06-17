<script setup lang="ts">
import { useRouter } from 'vue-router'
import { createLeagueRoute } from '../router'

const router = useRouter()
</script>

<template>
  <main class="app-shell flow-shell">
    <section class="panel">
      <div class="panel-head">
        <h1>Not found</h1>
      </div>
      <button class="button primary" type="button" @click="router.push(createLeagueRoute)">Create league</button>
    </section>
  </main>
</template>
