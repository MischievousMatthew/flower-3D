<template>
  <div class="link-picker">
    <label class="field">
      <span>{{ label }}</span>
      <select :value="type" class="input" @change="setType($event.target.value)">
        <option v-if="optional" value="">No button</option>
        <option value="products">All products</option>
        <option value="category">A category</option>
        <option value="occasion">An occasion</option>
        <option value="store">A store</option>
        <option value="article">A journal article</option>
        <option value="url">A page or web address</option>
      </select>
    </label>
    <label v-if="choices" class="field">
      <span>Which one</span>
      <select :value="value" class="input" @change="$emit('update:value', $event.target.value)">
        <option value="" disabled>Choose…</option>
        <option v-for="c in choices" :key="c.value" :value="c.value">{{ c.label }}</option>
      </select>
      <small v-if="!choices.length" class="hint">Nothing to choose yet.</small>
    </label>
    <label v-else-if="type === 'url'" class="field">
      <span>Address</span>
      <input :value="value" class="input" placeholder="/shop or https://…" @input="$emit('update:value', $event.target.value)" />
    </label>
  </div>
</template>

<script setup>
import { computed } from "vue";

/** "Where does this button go?" for banners, announcements and promotions */
const props = defineProps({
  type: { type: String, default: "" },
  value: { type: String, default: "" },
  options: { type: Object, default: () => ({}) },
  label: { type: String, default: "Button goes to" },
  optional: { type: Boolean, default: false },
});
const emit = defineEmits(["update:type", "update:value"]);

const choices = computed(
  () => ({ category: props.options.categories, occasion: props.options.occasions, store: props.options.stores, article: props.options.articles })[props.type] ?? null,
);

const setType = (type) => {
  emit("update:type", type);
  emit("update:value", "");
};
</script>

<style scoped>
.link-picker {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
}
.input {
  padding: 9px 11px;
  border: 1px solid #e2e8f0;
  border-radius: 9px;
  font-size: 13px;
  color: #1e2739;
  background: #fff;
  font-family: inherit;
}
.input:focus {
  outline: none;
  border-color: #7c6ff7;
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.hint {
  color: #94a3b8;
  font-weight: 400;
}
@media (max-width: 560px) {
  .link-picker {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
