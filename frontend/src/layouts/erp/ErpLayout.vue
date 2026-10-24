<template>
  <ErpShell>
    <router-view />
  </ErpShell>
</template>

<script setup>
// The route layout for HR and Finance. Both use this one file, so moving
// between them keeps the same sidebar and header instead of rebuilding them.
import ErpShell from "./ErpShell.vue";
</script>
