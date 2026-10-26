<template>
  <ScanStation
    title="To ship"
    subtitle="Scan each order as it leaves with the rider to mark it on the way."
    stage="to_ship"
    queue-status="to_processed"
    queue-title="Ready to go out"
    next-label="On the way"
    placeholder="Scan a delivery barcode to dispatch…"
    storage-key="shippedIds"
    tone="blue"
    empty-text="Nothing is waiting to go out."
    :steps="[
      'Hand the packed order to the rider.',
      'Scan its barcode before it leaves.',
      'It moves to <strong>On the way</strong> on its own.',
    ]"
  />
</template>

<script setup>
import ScanStation from "../../../../../components/procurement/ScanStation.vue";
</script>
