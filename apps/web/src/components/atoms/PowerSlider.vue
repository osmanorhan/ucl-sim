<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  id: string
  modelValue: number
  error?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const badgeColor = computed(() => {
  if (props.modelValue < 35) return '#e05c5c'
  if (props.modelValue < 65) return '#f59e0b'
  return '#22c55e'
})
</script>

<template>
  <div class="power-field">
    <div class="power-label-row">
      <span>Power</span>
      <span class="power-badge" :style="{ color: badgeColor }">{{ modelValue }}</span>
    </div>
    <input
      :id="id"
      type="range"
      class="power-slider"
      min="1"
      max="100"
      :value="modelValue"
      :style="{ '--pct': `${modelValue}%` }"
      :aria-invalid="error === undefined ? undefined : true"
      :aria-describedby="`${id}-error`"
      @input="emit('update:modelValue', Number(($event.target as HTMLInputElement).value))"
    />
    <span :id="`${id}-error`" class="field-error">{{ error }}</span>
  </div>
</template>

<style scoped>
.power-field {
  display: grid;
  gap: 0.35rem;
}

.power-label-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 650;
}

.power-badge {
  font-size: 0.88rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  transition: color 0.2s;
}

.power-slider {
  -webkit-appearance: none;
  appearance: none;
  width: 100%;
  height: 1.5rem;
  background: transparent;
  border: none;
  outline: none;
  cursor: pointer;
  padding: 0;
  margin: 0;
}

.power-slider::-webkit-slider-runnable-track {
  height: 8px;
  border-radius: 4px;
  background:
    linear-gradient(to right, #e05c5c, #f59e0b 40%, #22c55e 80%, #0f6b57)
    0 / var(--pct) 100% no-repeat,
    var(--border);
}

.power-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  border: 1.5px solid var(--border);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
  margin-top: -6px;
  cursor: pointer;
}

.power-slider::-moz-range-track {
  height: 8px;
  border-radius: 4px;
  background: var(--border);
}

.power-slider::-moz-range-progress {
  height: 8px;
  border-radius: 4px 0 0 4px;
  background: var(--primary);
}

.power-slider::-moz-range-thumb {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #fff;
  border: 1.5px solid var(--border);
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
  cursor: pointer;
}

.field-error {
  min-height: 1rem;
  color: var(--danger);
  font-size: 0.72rem;
  line-height: 1;
}
</style>
