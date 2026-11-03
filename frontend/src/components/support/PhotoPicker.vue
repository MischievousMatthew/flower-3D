<template>
  <div class="pp">
    <ul v-if="modelValue.length" class="pp-list" aria-label="Photos to send">
      <li v-for="(p, i) in modelValue" :key="p.url">
        <img :src="p.url" :alt="p.file.name" />
        <button type="button" class="pp-remove" :aria-label="`Remove ${p.file.name}`" :disabled="disabled" @click="remove(i)">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>
      </li>
    </ul>
    <label v-if="modelValue.length < max" class="pp-add" :class="{ disabled: disabled || preparing }">
      <input type="file" accept="image/jpeg,image/png,image/webp" multiple :disabled="disabled || preparing" @change="pick" />
      <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="9" cy="10" r="1.6" /><path d="m21 16-5-5-9 8" /></svg>
      {{ preparing ? "Getting photos ready…" : modelValue.length ? "Add another photo" : "Add photos" }}
    </label>
    <p v-if="problem" class="pp-error" role="alert">{{ problem }}</p>
  </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from "vue";
import { shrinkPhoto } from "../../utils/shrinkPhoto";

/**
 * Photos for a Help & support message: up to 4 JPG/PNG/WebP, 5 MB each after
 * big phone photos are shrunk. v-model is [{ file, url }] (url is a preview).
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  max: { type: Number, default: 4 },
  disabled: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);

const MAX_BYTES = 5 * 1024 * 1024;
const preparing = ref(false);
const problem = ref("");

async function pick(event) {
  const chosen = [...(event.target.files ?? [])];
  event.target.value = "";
  problem.value = "";
  if (!chosen.length) return;

  const room = props.max - props.modelValue.length;
  if (chosen.length > room) problem.value = `You can add up to ${props.max} photos.`;

  preparing.value = true;
  const added = [];
  for (const picked of chosen.slice(0, room)) {
    if (!/^image\/(jpeg|png|webp)$/.test(picked.type)) {
      problem.value = "Only photos can be attached (JPG, PNG or WebP).";
      continue;
    }
    const file = await shrinkPhoto(picked);
    if (file.size > MAX_BYTES) {
      problem.value = `${picked.name} is ${(file.size / 1048576).toFixed(1)} MB. Each photo can be up to 5 MB.`;
      continue;
    }
    added.push({ file, url: URL.createObjectURL(file) });
  }
  preparing.value = false;
  emit("update:modelValue", [...props.modelValue, ...added]);
}

function remove(i) {
  URL.revokeObjectURL(props.modelValue[i].url);
  emit("update:modelValue", props.modelValue.filter((_, k) => k !== i));
}

onBeforeUnmount(() => props.modelValue.forEach((p) => URL.revokeObjectURL(p.url)));
</script>

<style scoped>
.pp {
  display: grid;
  gap: 8px;
}
.pp-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.pp-list li {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid var(--line, var(--v-border, #e5e7eb));
}
.pp-list img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.pp-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border: 0;
  border-radius: 50%;
  background: rgba(15, 23, 42, 0.72);
  color: #fff;
  cursor: pointer;
}
.pp-remove svg {
  width: 14px;
  height: 14px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2.2;
  stroke-linecap: round;
}
.pp-add {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: fit-content;
  min-height: 36px;
  padding: 0 12px;
  border: 1px dashed var(--line, var(--v-border-strong, #cbd5e1));
  border-radius: 10px;
  color: var(--muted, var(--v-muted, #64748b));
  font-size: 13px;
  cursor: pointer;
}
.pp-add:hover:not(.disabled) {
  color: inherit;
  border-style: solid;
}
.pp-add:focus-within {
  outline: 2px solid var(--bc-accent, #4db173);
  outline-offset: 2px;
}
.pp-add.disabled {
  opacity: 0.6;
  cursor: default;
}
.pp-add input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.pp-add svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pp-error {
  margin: 0;
  font-size: 12.5px;
  color: #dc2626;
}
</style>
