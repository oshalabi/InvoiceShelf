<template>
  <BaseCard class="p-4">
    <p class="text-sm text-muted">{{ label }}</p>
    <BaseFormatMoney
      class="block mt-2 text-xl font-semibold whitespace-nowrap tabular-nums text-heading"
      :amount="Math.abs(amount)"
      :currency="currency"
    />
    <p v-if="creditLabel && amount < 0" class="mt-1 text-xs font-medium text-status-green">
      {{ creditLabel }}
    </p>
  </BaseCard>
</template>

<script setup lang="ts">
import type { Currency } from '@/scripts/types/domain/currency'

withDefaults(defineProps<{
  label: string
  amount: number
  currency?: Currency | Record<string, unknown> | null
  creditLabel?: string
}>(), {
  currency: null,
  creditLabel: '',
})
</script>
