<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'button' | 'submit'
  variant?: 'primary' | 'secondary'
  disabled?: boolean
}>(), {
  type: 'button',
  variant: 'secondary',
  disabled: false,
})
</script>

<template>
  <button class="button" :class="variant" :type="type" :disabled="disabled">
    <slot />
  </button>
</template>

<style scoped>
.button {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.42rem 0.65rem;
  background: var(--surface-subtle);
  color: var(--text);
  font-weight: 650;
}

.button.primary {
  border-color: var(--primary);
  background: var(--primary);
  color: #fff;
}

.button.primary:hover:not(:disabled) {
  background: var(--primary-strong);
}
</style>
