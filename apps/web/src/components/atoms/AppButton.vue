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
