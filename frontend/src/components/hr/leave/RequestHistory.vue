<template>
  <ol class="rh" aria-label="History">
    <li v-for="(e, i) in history" :key="i" :class="e.action">
      <i aria-hidden="true"></i>
      <div>
        <b>{{ labels[e.action] ?? e.action }}</b>
        <small>{{ [e.by, when(e.at)].filter(Boolean).join(" · ") }}</small>
        <p v-if="e.note">{{ e.note }}</p>
      </div>
    </li>
  </ol>
</template>

<script setup>
import { EVENT_LABEL } from "../../../services/leaveDesk";

defineProps({
  history: { type: Array, default: () => [] },
  // action → words; leave and overtime wording by default
  labels: { type: Object, default: () => EVENT_LABEL },
});

const when = (iso) => (iso ? new Date(iso).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "");
</script>

<style scoped>
.rh {
  margin: 0;
  padding: 0;
  list-style: none;
}
.rh li {
  position: relative;
  display: flex;
  gap: 10px;
  padding-bottom: 12px;
}
.rh li:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 5px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: var(--v-border);
}
.rh i {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border-radius: 50%;
  background: var(--v-faint);
}
.rh .approved i,
.rh .paid i { background: #48bb78; }
.rh .rejected i { background: #e53e3e; }
.rh .filed i,
.rh .created i,
.rh .resubmitted i { background: #4299e1; }
.rh b {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
}
.rh small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.rh p {
  margin: 4px 0 0;
  font-size: 12.5px;
  color: var(--v-text-2);
}
</style>
