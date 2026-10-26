<template>
  <ScanStation
    title="To process"
    subtitle="Scan each packed order's barcode to mark it ready to ship."
    stage="to_process"
    queue-status="pending"
    queue-title="Waiting to be processed"
    next-label="To ship"
    placeholder="Scan a delivery barcode to process…"
    storage-key="processedIds"
    tone="amber"
    empty-text="Every order has been processed."
    :steps="[
      'Pack the order and find its barcode label.',
      'Scan the <strong>BLM-</strong> barcode on the label.',
      'It moves to <strong>To ship</strong> on its own.',
    ]"
  />
</template>

<script setup>
import ScanStation from "../../../../../components/procurement/ScanStation.vue";
</script>
