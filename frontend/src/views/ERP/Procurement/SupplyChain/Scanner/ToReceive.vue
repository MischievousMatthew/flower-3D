<template>
  <ScanStation
    title="To receive"
    subtitle="Scan the package at the customer's door. The customer is then asked to confirm they got it."
    stage="to_receive"
    queue-status="to_ship"
    queue-title="On the way"
    next-label="To receive"
    placeholder="Scan a delivery barcode to confirm drop-off…"
    storage-key="receivedIds"
    tone="violet"
    empty-text="No deliveries are on the road right now."
    :steps="[
      'Arrive at the delivery address with the order.',
      'Scan the barcode on the package.',
      'It moves to <strong>To receive</strong> and the customer is asked to confirm.',
    ]"
  />
</template>

<script setup>
import ScanStation from "../../../../../components/procurement/ScanStation.vue";
</script>
