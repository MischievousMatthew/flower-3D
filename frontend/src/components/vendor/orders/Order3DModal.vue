<template>
  <Teleport to="body">
    <Transition name="sp-fade"><div v-if="model" class="sp-backdrop" @click="$emit('close')"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="model" class="sp-modal-wrap">
        <div class="sp-modal wide m3d" role="dialog" aria-modal="true" aria-labelledby="m3d-title">
          <div class="sp-modal-head">
            <div>
              <h3 id="m3d-title">{{ model.title }}</h3>
              <p>Drag to turn it · scroll to zoom · double-click to reset</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="$emit('close')"><span v-html="ICONS.close"></span></button>
          </div>
          <div class="m3d-stage">
            <ThreeDModelViewer :model-url="model.url" :model-type="model.type" :arrangement="model.arrangement" background-color="#f4f7f5" />
          </div>
          <div v-if="model.arrangement" class="m3d-colors">
            <span><i :style="{ background: model.arrangement.paper_color }"></i>Wrapper</span>
            <span><i :style="{ background: model.arrangement.ribbon_color }"></i>Ribbon</span>
            <span>{{ model.arrangement.flowers?.length || 0 }} flowers placed</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import ThreeDModelViewer from "../../../layouts/3D/3DModelViewer.vue";
import { SHOP_ICONS as ICONS } from "../shop/shopIcons";

// model: { title, url, type, arrangement }
defineProps({ model: { type: Object, default: null } });
defineEmits(["close"]);
</script>

<style scoped>
.m3d {
  width: min(820px, 100%);
}
.m3d-stage {
  height: min(60vh, 520px);
  border-radius: 16px;
  overflow: hidden;
  background: #f4f7f5;
}
.m3d-stage :deep(> *) {
  width: 100%;
  height: 100%;
}
.m3d-colors {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-top: 12px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.m3d-colors span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.m3d-colors i {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.1);
}
</style>
