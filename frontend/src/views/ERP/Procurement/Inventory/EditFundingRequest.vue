<template>
  <FundingRequestForm :id="id" />
</template>

<script setup>
import FundingRequestForm from "../../../../components/procurement/FundingRequestForm.vue";

defineProps({
  id: { type: [String, Number], default: null },
});
</script>
