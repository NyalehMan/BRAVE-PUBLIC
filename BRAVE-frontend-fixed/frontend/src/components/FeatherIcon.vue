<template>
  <span v-html="svg" class="feather-icon" />
</template>

<script setup>
import { computed } from 'vue'
import feather from 'feather-icons'

const props = defineProps({
  icon: {
    type: String,
    required: true,
  },
  size: {
    type: Number,
    default: 18,
  },
})

const svg = computed(
  () =>
    feather.icons[props.icon]?.toSvg({
      width: props.size,
      height: props.size,
    }) || '',
)
</script>

<style scoped>
.feather-icon svg {
  display: block;
}
</style>
