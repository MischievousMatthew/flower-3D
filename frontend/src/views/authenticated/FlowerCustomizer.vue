<template>
  <div class="fc-page bc-theme">
    <NavHeader :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <div class="fc-shell">
      <header class="fc-head">
        <div class="fc-head-copy">
          <p class="fc-store">{{ storeName ? `Arranged by ${storeName}` : "Custom bouquet" }}</p>
          <h1>Build your bouquet</h1>
          <p class="fc-lede">Choose up to {{ MAX_FLOWERS }} stems, set them on the wrap, then finish with a wrapper and ribbon.</p>
        </div>

        <div class="fc-head-meta">
          <ol class="fc-stems" aria-label="Stems on the bouquet">
            <li
              v-for="(slot, index) in stemSlots"
              :key="index"
              class="fc-stem"
              :class="{ filled: slot, active: slot && slot.sceneId === selectedSceneId }"
            >
              <button v-if="slot" type="button" :aria-label="`Select ${slot.product_name}`" @click="selectPlacedFlower(slot.sceneId)">
                <img v-if="flowerImage(slot.id)" :src="flowerImage(slot.id)" alt="" />
                <BcIcon v-else name="flower" />
              </button>
              <span v-else class="fc-stem-empty" aria-label="Empty stem slot"><BcIcon name="plus" /></span>
            </li>
          </ol>
          <div class="fc-head-total">
            <span>{{ placedFlowers.length }} of {{ MAX_FLOWERS }} stems</span>
            <strong>{{ formatPeso(totalPrice) }}</strong>
          </div>
        </div>
      </header>

      <div
        ref="layoutContainer"
        class="fc-layout"
        :class="{ 'left-collapsed': isLeftSidebarCollapsed, 'right-collapsed': isRightSidebarCollapsed, fullscreen: isFullscreen }"
      >
        <!-- Catalog -->
        <aside v-if="!isLeftSidebarCollapsed" class="fc-panel fc-catalog" aria-label="Flowers and fillers">
          <div class="fc-panel-head">
            <h2>Flowers</h2>
            <span v-if="flowers.length">{{ filteredFlowers.length }} available</span>
          </div>

          <label class="fc-search">
            <span class="bc-sr-only">Search flowers</span>
            <BcIcon name="search" />
            <input v-model="search" type="search" placeholder="Search flowers" />
          </label>

          <div class="fc-catalog-scroll">
            <p v-if="!storeId" class="fc-note">Open the customizer from a flower shop's page to see the stems they offer.</p>

            <div v-else-if="loadingFlowers" class="fc-grid" aria-busy="true" aria-label="Loading flowers">
              <div v-for="n in 4" :key="n" class="fc-tile is-skeleton">
                <div class="fc-tile-media bc-skeleton"></div>
                <div class="bc-skeleton fc-skel-line"></div>
                <div class="bc-skeleton fc-skel-line short"></div>
              </div>
            </div>

            <p v-else-if="!filteredFlowers.length" class="fc-note">
              {{ search ? `No flowers match “${search}”.` : "This shop hasn't added any stems for custom bouquets yet." }}
            </p>

            <div v-else class="fc-grid">
              <div
                v-for="flower in filteredFlowers"
                :key="flower.id"
                role="button"
                tabindex="0"
                class="fc-tile"
                :class="{
                  active: pendingFlower?.kind !== 'filler' && pendingFlower?.id === flower.id,
                  disabled: !canUseFlower(flower),
                  dragging: draggedFlower?.kind !== 'filler' && draggedFlower?.id === flower.id,
                }"
                :aria-disabled="!canUseFlower(flower)"
                :draggable="canUseFlower(flower)"
                @dragstart="onDragStart($event, flower)"
                @dragend="onDragEnd"
                @click="selectFlower(flower)"
                @keydown.enter.prevent="selectFlower(flower)"
                @keydown.space.prevent="selectFlower(flower)"
              >
                <span class="fc-tile-media">
                  <img v-if="flower.image" :src="flower.image" :alt="flower.product_name" />
                  <BcIcon v-else name="flower" class="fc-tile-fallback" />
                  <span v-if="countOfFlower(flower.id)" class="fc-tile-count">{{ countOfFlower(flower.id) }}</span>
                </span>
                <span class="fc-tile-name">{{ flower.product_name }}</span>
                <span class="fc-tile-meta">
                  <span>{{ formatPeso(flower.price) }}</span>
                  <span v-if="!canUseFlower(flower)" class="fc-tile-flag">{{ placedFlowers.length >= MAX_FLOWERS ? "Full" : "Sold out" }}</span>
                  <span v-else class="fc-tile-stock">{{ flower.quantity_in_stock }} left</span>
                </span>
              </div>
            </div>

            <template v-if="fillers.length">
              <div class="fc-panel-head fc-subhead">
                <h2>Fillers</h2>
                <span>{{ placedFillers.length }} of {{ MAX_FILLERS }} used</span>
              </div>
              <ul class="fc-fillers">
                <li v-for="filler in filteredFillers" :key="`filler-${filler.id}`">
                  <div
                    role="button"
                    tabindex="0"
                    class="fc-filler"
                    :class="{
                      active: pendingFlower?.kind === 'filler' && pendingFlower?.id === filler.id,
                      disabled: !canUseFlower(filler),
                      dragging: draggedFlower?.kind === 'filler' && draggedFlower?.id === filler.id,
                    }"
                    :aria-disabled="!canUseFlower(filler)"
                    :draggable="canUseFlower(filler)"
                    @dragstart="onDragStart($event, filler)"
                    @dragend="onDragEnd"
                    @click="selectFlower(filler)"
                    @keydown.enter.prevent="selectFlower(filler)"
                    @keydown.space.prevent="selectFlower(filler)"
                  >
                    <span class="fc-filler-icon"><BcIcon name="leaf" /></span>
                    <span class="fc-filler-copy">
                      <strong>{{ filler.product_name }}</strong>
                      <small v-if="filler.description">{{ filler.description }}</small>
                    </span>
                    <span class="fc-filler-price">
                      {{ filler.price ? `+${formatPeso(filler.price)}` : "Free" }}
                      <small v-if="countOfFlower(filler.id, 'filler')">{{ countOfFlower(filler.id, "filler") }} on bouquet</small>
                    </span>
                  </div>
                </li>
              </ul>
            </template>
          </div>
        </aside>

        <!-- Stage -->
        <main
          class="fc-stage"
          :class="{ over: isDragOver, dragging: isTransformDragging, placing: pendingFlower || draggedFlower }"
          @dragover.prevent="onCanvasDragOver"
          @dragleave="onCanvasDragLeave"
          @drop.prevent="onCanvasDrop"
        >
          <div ref="viewerContainer" class="fc-viewer"></div>

          <div class="fc-toolbar fc-toolbar-left">
            <button
              type="button"
              class="fc-tool"
              :class="{ on: !isLeftSidebarCollapsed }"
              :data-tip="isLeftSidebarCollapsed ? 'Show flowers' : 'Hide flowers'"
              :aria-label="isLeftSidebarCollapsed ? 'Show flowers' : 'Hide flowers'"
              :aria-pressed="!isLeftSidebarCollapsed"
              @click="toggleLeftSidebar"
            >
              <BcIcon name="panelLeft" />
            </button>
          </div>

          <div class="fc-toolbar fc-toolbar-right">
            <button type="button" class="fc-tool" data-tip="Start over" aria-label="Start over" :disabled="!selectedFlowers.length" @click="resetDesign">
              <BcIcon name="reset" />
            </button>
            <button type="button" class="fc-tool" data-tip="Save as image" aria-label="Save as image" :disabled="!sceneReady" @click="takeScreenshot">
              <BcIcon name="camera" />
            </button>
            <button
              type="button"
              class="fc-tool"
              :data-tip="isFullscreen ? 'Exit full screen' : 'Full screen'"
              :aria-label="isFullscreen ? 'Exit full screen' : 'Full screen'"
              :disabled="!sceneReady"
              @click="toggleFullscreen"
            >
              <BcIcon :name="isFullscreen ? 'minimize' : 'maximize'" />
            </button>
            <span class="fc-toolbar-rule" aria-hidden="true"></span>
            <button
              type="button"
              class="fc-tool"
              :class="{ on: !isRightSidebarCollapsed }"
              :data-tip="isRightSidebarCollapsed ? 'Show details' : 'Hide details'"
              :aria-label="isRightSidebarCollapsed ? 'Show details' : 'Hide details'"
              :aria-pressed="!isRightSidebarCollapsed"
              @click="toggleRightSidebar"
            >
              <BcIcon name="panelRight" />
            </button>
          </div>

          <div v-if="bouquetLoadError" class="fc-stage-center">
            <div class="fc-stage-message">
              <BcIcon name="info" />
              <h3>The bouquet couldn't load</h3>
              <p>{{ bouquetLoadError }}</p>
            </div>
          </div>
          <div v-else-if="isLoading3D" class="fc-stage-center">
            <div class="fc-stage-loading">
              <span class="bc-skeleton fc-skel-arch"></span>
              <p>Setting up the bouquet</p>
            </div>
          </div>

          <div v-if="sceneReady" class="fc-hint" :class="{ live: dragFeedback || pendingFlower }" role="status" aria-live="polite">
            <BcIcon :name="pendingFlower || draggedFlower ? 'target' : selectedFlower ? 'move' : 'rotate'" />
            <span class="fc-hint-copy">
              <strong>{{ dragFeedback || stageHint.title }}</strong>
              <span v-if="!dragFeedback">{{ stageHint.detail }}</span>
            </span>
            <button v-if="pendingFlower" type="button" class="fc-hint-cancel" aria-label="Cancel placing" @click="pendingFlower = null">
              <BcIcon name="x" />
            </button>
          </div>
        </main>

        <!-- Inspector -->
        <aside v-if="!isRightSidebarCollapsed" class="fc-panel fc-inspector" aria-label="Bouquet details">
          <div class="fc-inspector-scroll">
            <section class="fc-section">
              <div class="fc-panel-head">
                <h2>On the bouquet</h2>
                <span v-if="selectedFlowers.length">{{ selectedFlowers.length }} item{{ selectedFlowers.length === 1 ? "" : "s" }}</span>
              </div>
              <p v-if="!selectedFlowers.length" class="fc-note">Nothing placed yet. Pick a flower on the left, then click the wrap.</p>
              <ul v-else class="fc-placed">
                <li
                  v-for="selection in selectedFlowers"
                  :key="selection.sceneId"
                  class="fc-placed-row"
                  :class="{ active: selectedSceneId === selection.sceneId }"
                >
                  <button type="button" class="fc-placed-main" @click="selectPlacedFlower(selection.sceneId)">
                    <span class="fc-placed-thumb">
                      <img v-if="selection.kind !== 'filler' && flowerImage(selection.id)" :src="flowerImage(selection.id)" alt="" />
                      <BcIcon v-else :name="selection.kind === 'filler' ? 'leaf' : 'flower'" />
                    </span>
                    <span class="fc-placed-copy">
                      <strong>{{ selection.product_name }}</strong>
                      <small>{{ selection.kind === "filler" ? "Filler" : "Stem" }} · {{ formatPeso(selection.price) }}</small>
                    </span>
                  </button>
                  <button
                    type="button"
                    class="fc-mini"
                    :class="{ on: selection.locked }"
                    :aria-label="selection.locked ? `Unlock ${selection.product_name}` : `Lock ${selection.product_name}`"
                    :title="selection.locked ? 'Unlock position' : 'Lock position'"
                    @click="toggleFlowerLock(selection.sceneId)"
                  >
                    <BcIcon :name="selection.locked ? 'lock' : 'unlock'" />
                  </button>
                  <button
                    type="button"
                    class="fc-mini danger"
                    :aria-label="`Remove ${selection.product_name}`"
                    title="Remove"
                    @click="removeFlower(selection.sceneId)"
                  >
                    <BcIcon name="trash" />
                  </button>
                </li>
              </ul>
            </section>

            <section class="fc-section">
              <div class="fc-panel-head">
                <h2>Adjust</h2>
                <span v-if="selectedFlower" class="fc-truncate">{{ selectedFlower.product_name }}</span>
              </div>
              <p v-if="!selectedFlower" class="fc-note">Select something on the bouquet to resize, turn or move it.</p>

              <div v-else class="fc-adjust">
                <button
                  type="button"
                  class="fc-switch"
                  role="switch"
                  :aria-checked="selectedFlower.locked"
                  @click="toggleSelectedLock"
                >
                  <span class="fc-switch-copy">
                    <strong><BcIcon :name="selectedFlower.locked ? 'lock' : 'unlock'" /> Hold position</strong>
                    <small>{{ selectedFlower.locked ? "Dragging and arrows are off." : "Drag it on the wrap or use the arrows." }}</small>
                  </span>
                  <span class="fc-switch-track"><span class="fc-switch-knob"></span></span>
                </button>

                <div class="fc-control">
                  <div class="fc-control-head">
                    <label for="fc-size">Size</label>
                    <output>{{ Number(selectedFlower.scale ?? 1).toFixed(2) }}&times;</output>
                  </div>
                  <input
                    id="fc-size"
                    class="fc-range"
                    type="range"
                    min="0.25"
                    max="2"
                    step="0.01"
                    :value="selectedFlower.scale ?? 1"
                    :style="rangeFill(selectedFlower.scale ?? 1, 0.25, 2)"
                    @input="updateSelectedScale(Number($event.target.value))"
                  />
                </div>

                <div class="fc-control">
                  <div class="fc-control-head">
                    <label for="fc-tilt">Tilt</label>
                    <output>{{ Math.round(selectedFlower.rotation.xDeg) }}&deg;</output>
                  </div>
                  <div class="fc-control-row">
                    <button type="button" class="fc-step" aria-label="Tilt back 15 degrees" @click="nudgeSelectedRotation('x', -15)"><BcIcon name="minus" /></button>
                    <input
                      id="fc-tilt"
                      class="fc-range"
                      type="range"
                      min="-180"
                      max="180"
                      :value="selectedFlower.rotation.xDeg"
                      :style="rangeFill(selectedFlower.rotation.xDeg, -180, 180)"
                      @input="updateSelectedRotation('x', Number($event.target.value))"
                    />
                    <button type="button" class="fc-step" aria-label="Tilt forward 15 degrees" @click="nudgeSelectedRotation('x', 15)"><BcIcon name="plus" /></button>
                  </div>
                </div>

                <div class="fc-control">
                  <div class="fc-control-head">
                    <label for="fc-turn">Turn</label>
                    <output>{{ Math.round(selectedFlower.rotation.yDeg) }}&deg;</output>
                  </div>
                  <div class="fc-control-row">
                    <button type="button" class="fc-step" aria-label="Turn left 15 degrees" @click="nudgeSelectedRotation('y', -15)"><BcIcon name="minus" /></button>
                    <input
                      id="fc-turn"
                      class="fc-range"
                      type="range"
                      min="-180"
                      max="180"
                      :value="selectedFlower.rotation.yDeg"
                      :style="rangeFill(selectedFlower.rotation.yDeg, -180, 180)"
                      @input="updateSelectedRotation('y', Number($event.target.value))"
                    />
                    <button type="button" class="fc-step" aria-label="Turn right 15 degrees" @click="nudgeSelectedRotation('y', 15)"><BcIcon name="plus" /></button>
                  </div>
                </div>

                <div class="fc-move">
                  <div class="fc-move-copy">
                    <span>Position</span>
                    <small>Moves in small steps</small>
                  </div>
                  <div class="fc-pad" :class="{ locked: selectedFlower.locked }">
                    <button type="button" class="fc-pad-up" aria-label="Move up" :disabled="selectedFlower.locked" @click="nudgeSelectedPosition('up')"><BcIcon name="arrowUp" /></button>
                    <button type="button" class="fc-pad-left" aria-label="Move left" :disabled="selectedFlower.locked" @click="nudgeSelectedPosition('left')"><BcIcon name="arrowLeft" /></button>
                    <button type="button" class="fc-pad-right" aria-label="Move right" :disabled="selectedFlower.locked" @click="nudgeSelectedPosition('right')"><BcIcon name="arrowRight" /></button>
                    <button type="button" class="fc-pad-down" aria-label="Move down" :disabled="selectedFlower.locked" @click="nudgeSelectedPosition('down')"><BcIcon name="arrowDown" /></button>
                  </div>
                </div>

                <button type="button" class="fc-text-btn" @click="resetSelectedTransform">
                  <BcIcon name="reset" /> Reset size and angle
                </button>
              </div>
            </section>

            <section class="fc-section">
              <div class="fc-panel-head">
                <h2>Wrapper</h2>
                <span>{{ wrap.outer.color || wrap.inner.color ? "Custom" : "Original paper" }}</span>
              </div>

              <div class="fc-segment" role="tablist" aria-label="Wrapper layer">
                <button
                  v-for="layer in WRAP_LAYERS"
                  :key="layer.key"
                  type="button"
                  role="tab"
                  class="fc-segment-btn"
                  :class="{ active: activeWrapLayer === layer.key }"
                  :aria-selected="activeWrapLayer === layer.key"
                  @click="activeWrapLayer = layer.key"
                >
                  <span class="fc-segment-dot" :style="{ background: wrap[layer.key].color || ORIGINAL_WRAP[layer.key] }"></span>
                  {{ layer.label }}
                </button>
              </div>

              <div v-if="wrapperOptions.length" class="fc-options" role="radiogroup" :aria-label="`${activeWrapLayer === 'outer' ? 'Outer' : 'Inner'} wrapper`">
                <button
                  type="button"
                  role="radio"
                  class="fc-option"
                  :class="{ active: !activeWrap.id && !activeWrap.color }"
                  :aria-checked="!activeWrap.id && !activeWrap.color"
                  @click="selectWrapper(activeWrapLayer, null)"
                >
                  <span class="fc-option-swatch" :style="{ background: ORIGINAL_WRAP[activeWrapLayer] }"></span>
                  <span class="fc-option-copy">
                    <strong>Original</strong>
                    <small>The paper this bouquet comes with</small>
                  </span>
                  <span class="fc-option-price">Free</span>
                  <BcIcon v-if="!activeWrap.id && !activeWrap.color" name="check" class="fc-option-check" />
                </button>
                <button
                  v-for="option in wrapperOptions"
                  :key="option.id"
                  type="button"
                  role="radio"
                  class="fc-option"
                  :class="{ active: activeWrap.id === option.id }"
                  :aria-checked="activeWrap.id === option.id"
                  @click="selectWrapper(activeWrapLayer, option)"
                >
                  <span class="fc-option-swatch" :style="{ background: option.color_hex }"></span>
                  <span class="fc-option-copy">
                    <strong>{{ option.name }}</strong>
                    <small v-if="option.description">{{ option.description }}</small>
                  </span>
                  <span class="fc-option-price">{{ option.price ? `+${formatPeso(option.price)}` : "Free" }}</span>
                  <BcIcon v-if="activeWrap.id === option.id" name="check" class="fc-option-check" />
                </button>
              </div>
              <div v-else class="fc-swatches">
                <button
                  type="button"
                  class="fc-swatch fc-swatch-original"
                  :class="{ active: !activeWrap.color }"
                  :style="{ background: ORIGINAL_WRAP[activeWrapLayer] }"
                  title="Original paper"
                  aria-label="Original paper"
                  @click="applyWrapColor(activeWrapLayer, null)"
                ><BcIcon name="reset" /></button>
                <button
                  v-for="color in paperPresets"
                  :key="color"
                  type="button"
                  class="fc-swatch"
                  :class="{ active: activeWrap.color === color }"
                  :style="{ background: color }"
                  :aria-label="`${activeWrapLayer === 'outer' ? 'Outer' : 'Inner'} wrapper colour ${color}`"
                  @click="pickWrapPreset(color)"
                ></button>
                <label class="fc-swatch fc-swatch-custom" title="Custom colour">
                  <span class="bc-sr-only">Custom wrapper colour</span>
                  <input type="color" :value="activeWrap.color || ORIGINAL_WRAP[activeWrapLayer]" @input="pickWrapPreset($event.target.value)" />
                  <BcIcon name="plus" />
                </label>
              </div>
            </section>

            <section class="fc-section">
              <div class="fc-panel-head">
                <h2>Ribbon</h2>
                <span v-if="!ribbonOptions.length">{{ ribbonColor.toUpperCase() }}</span>
              </div>
              <div v-if="ribbonOptions.length" class="fc-options" role="radiogroup" aria-label="Ribbon">
                <button
                  v-for="option in ribbonOptions"
                  :key="option.id"
                  type="button"
                  role="radio"
                  class="fc-option"
                  :class="{ active: selectedRibbonId === option.id }"
                  :aria-checked="selectedRibbonId === option.id"
                  @click="selectRibbon(option)"
                >
                  <span class="fc-option-swatch" :style="{ background: option.color_hex }"></span>
                  <span class="fc-option-copy">
                    <strong>{{ option.name }}</strong>
                    <small v-if="option.description">{{ option.description }}</small>
                  </span>
                  <span class="fc-option-price">{{ option.price ? `+${formatPeso(option.price)}` : "Free" }}</span>
                  <BcIcon v-if="selectedRibbonId === option.id" name="check" class="fc-option-check" />
                </button>
              </div>
              <div v-else class="fc-swatches">
                <button
                  v-for="color in ribbonPresets"
                  :key="color"
                  type="button"
                  class="fc-swatch"
                  :class="{ active: ribbonColor === color }"
                  :style="{ background: color }"
                  :aria-label="`Ribbon colour ${color}`"
                  @click="applyRibbonColor(color)"
                ></button>
                <label class="fc-swatch fc-swatch-custom" title="Custom colour">
                  <span class="bc-sr-only">Custom ribbon colour</span>
                  <input v-model="ribbonColor" type="color" @input="applyRibbonColor(ribbonColor)" />
                  <BcIcon name="plus" />
                </label>
              </div>
            </section>
          </div>

          <footer class="fc-order">
            <dl class="fc-receipt">
              <div><dt>Stems ({{ placedFlowers.length }})</dt><dd>{{ formatPeso(flowersSubtotal) }}</dd></div>
              <div v-if="placedFillers.length"><dt>Fillers ({{ placedFillers.length }})</dt><dd>{{ formatPeso(fillersSubtotal) }}</dd></div>
              <div v-if="selectedOuterWrapper"><dt>Outer: {{ selectedOuterWrapper.name }}</dt><dd>{{ selectedOuterWrapper.price ? formatPeso(selectedOuterWrapper.price) : "Free" }}</dd></div>
              <div v-if="selectedInnerWrapper"><dt>Inner: {{ selectedInnerWrapper.name }}</dt><dd>{{ selectedInnerWrapper.price ? formatPeso(selectedInnerWrapper.price) : "Free" }}</dd></div>
              <div v-if="selectedRibbon"><dt>{{ selectedRibbon.name }}</dt><dd>{{ selectedRibbon.price ? formatPeso(selectedRibbon.price) : "Free" }}</dd></div>
              <div class="fc-receipt-total"><dt>Total</dt><dd>{{ formatPeso(totalPrice) }}</dd></div>
            </dl>
            <div class="fc-order-actions">
              <button type="button" class="bc-btn bc-btn--dark fc-order-btn" :disabled="!placedFlowers.length || isCheckingOut" @click="checkout">
                {{ isCheckingOut ? "Processing" : "Checkout" }}
              </button>
              <button
                type="button"
                class="bc-btn fc-order-btn fc-order-btn-outline"
                :disabled="!placedFlowers.length || isCheckingOut"
                @click="addCart"
              >
                <BcIcon name="bag" />
                {{ editingCartItemId ? "Update cart" : "Add to cart" }}
              </button>
            </div>
            <p v-if="!placedFlowers.length" class="fc-order-hint">Add at least one stem to continue.</p>
          </footer>
        </aside>
      </div>
    </div>

    <transition name="fc-toast">
      <div v-if="toast.show" class="fc-toast" :class="toast.type" role="status">
        <BcIcon :name="toast.type === 'error' ? 'info' : 'check'" />
        <span>{{ toast.message }}</span>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, h, onMounted, onUnmounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { OrbitControls } from "three/examples/jsm/controls/OrbitControls.js";
import * as SkeletonUtils from "three/examples/jsm/utils/SkeletonUtils.js";
import api from "../../plugins/axios.js";
import { dressBouquet } from "../../utils/bouquetDressing.js";
import NavHeader from "../../layouts/NavHeader.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";
import { toast as toastify } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import "../../styles/bloomcraft.css";

// Line icons drawn on a 24px grid with a 1.5 stroke, matching Shop.vue.
const ICONS = {
  search: [["circle", { cx: 11, cy: 11, r: 6.5 }], ["path", { d: "m16 16 4 4" }]],
  panelLeft: [["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }], ["path", { d: "M9 4v16" }]],
  panelRight: [["rect", { x: 3, y: 4, width: 18, height: 16, rx: 2 }], ["path", { d: "M15 4v16" }]],
  maximize: [["path", { d: "M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" }]],
  minimize: [["path", { d: "M9 4v5H4M15 4v5h5M9 20v-5H4M15 20v-5h5" }]],
  camera: [["path", { d: "M4 8h3l2-3h6l2 3h3v11H4z" }], ["circle", { cx: 12, cy: 13, r: 3.5 }]],
  reset: [["path", { d: "M4 12a8 8 0 1 0 2.4-5.7L4 8.5" }], ["path", { d: "M4 4v4.5h4.5" }]],
  rotate: [["path", { d: "M20 12a8 8 0 1 1-2.4-5.7L20 8.5" }], ["path", { d: "M20 4v4.5h-4.5" }]],
  lock: [["rect", { x: 5, y: 11, width: 14, height: 9, rx: 2 }], ["path", { d: "M8 11V8a4 4 0 0 1 8 0v3" }]],
  unlock: [["rect", { x: 5, y: 11, width: 14, height: 9, rx: 2 }], ["path", { d: "M8 11V8a4 4 0 0 1 7.6-1.7" }]],
  trash: [["path", { d: "M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" }]],
  arrowUp: [["path", { d: "M12 19V5M6 11l6-6 6 6" }]],
  arrowDown: [["path", { d: "M12 5v14M6 13l6 6 6-6" }]],
  arrowLeft: [["path", { d: "M19 12H5M11 6l-6 6 6 6" }]],
  arrowRight: [["path", { d: "M5 12h14M13 6l6 6-6 6" }]],
  plus: [["path", { d: "M12 5v14M5 12h14" }]],
  minus: [["path", { d: "M5 12h14" }]],
  check: [["path", { d: "m5 12.5 4.5 4.5L19 7.5" }]],
  x: [["path", { d: "M6 6l12 12M18 6 6 18" }]],
  info: [["circle", { cx: 12, cy: 12, r: 9 }], ["path", { d: "M12 11v5M12 8h.01" }]],
  bag: [["path", { d: "M5 8h14l-1 12H6z" }], ["path", { d: "M9 8V6.5a3 3 0 0 1 6 0V8" }]],
  move: [["path", { d: "M12 3v18M3 12h18M9 6l3-3 3 3M9 18l3 3 3-3M6 9l-3 3 3 3M18 9l3 3-3 3" }]],
  target: [["circle", { cx: 12, cy: 12, r: 7 }], ["circle", { cx: 12, cy: 12, r: 2 }], ["path", { d: "M12 2v3M12 19v3M2 12h3M19 12h3" }]],
  leaf: [["path", { d: "M5 19c0-8 5-14 14-14 0 9-6 14-14 14z" }], ["path", { d: "M5 19l7.5-7.5" }]],
  flower: [
    ["circle", { cx: 12, cy: 9, r: 2 }],
    ["path", { d: "M12 7a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5M14 9a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0M12 11a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5M10 9a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0" }],
    ["path", { d: "M12 16v6M12 19.5c-1.5-1.8-3.5-2.2-5-1.8" }],
  ],
};
const BcIcon = (props) =>
  h(
    "svg",
    { class: "fc-icon", viewBox: "0 0 24 24", "aria-hidden": "true", focusable: "false" },
    (ICONS[props.name] || []).map(([tag, attrs]) => h(tag, attrs)),
  );
BcIcon.props = ["name"];

const MAX_FLOWERS = 3;
const MAX_FILLERS = 5;
// Used only when the vendor has not added their own wrappers or ribbons.
const paperPresets = ["#d3b18f", "#f3e9dc", "#d7d2c8", "#e2c7cf", "#c8b6a6"];
// Approximate colours of the model's own papers, for the "Original" swatch.
const ORIGINAL_WRAP = { outer: "#d6b68c", inner: "#ab9196" };
const WRAP_LAYERS = [
  { key: "outer", label: "Outer" },
  { key: "inner", label: "Inner" },
];
const ribbonPresets = ["#caa6d9", "#d95f76", "#7d8f69", "#f0dfb2", "#6c5b7b"];

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const cartStore = useCart();

const viewerContainer = ref(null);
const layoutContainer = ref(null);
const flowers = ref([]);
const fillers = ref([]);
const wrapperOptions = ref([]);
const ribbonOptions = ref([]);
// Each wrap layer: the vendor option picked (id) and its colour; null colour = original paper.
const wrap = reactive({ outer: { id: null, color: null }, inner: { id: null, color: null } });
const activeWrapLayer = ref("outer");
const selectedRibbonId = ref(null);
const loadingFlowers = ref(false);
const search = ref("");
const pendingFlower = ref(null);
const draggedFlower = ref(null);
const selectedFlowers = ref([]);
const selectedSceneId = ref(null);
const isDragOver = ref(false);
const isTransformDragging = ref(false);
const isLeftSidebarCollapsed = ref(false);
const isRightSidebarCollapsed = ref(false);
const isFullscreen = ref(false);
const dragFeedback = ref("");
const ribbonColor = ref("#caa6d9");
const wrapperScale = ref(1);
const isLoading3D = ref(true);
const sceneReady = ref(false);
const bouquetLoadError = ref("");
const isCheckingOut = ref(false);
const toast = reactive({ show: false, message: "", type: "success" });

const editingCartItemId = computed(() => String(route.query.cart_item_id || ""));
const editingCustomization = ref(null);
const storeId = computed(() => String(editingCustomization.value?.store_id || route.query.store_id || route.params.id || ""));
const vendorOwnerId = computed(() => Number(editingCustomization.value?.vendor_id || route.query.owner_id || 0) || null);
const storeName = computed(() => String(route.query.store_name || ""));
const cartCount = computed(() => cartStore.count?.value ?? 0);
const filteredFlowers = computed(() => {
  const term = search.value.trim().toLowerCase();
  return flowers.value.filter((flower) => !term || flower.product_name.toLowerCase().includes(term));
});
const filteredFillers = computed(() => {
  const term = search.value.trim().toLowerCase();
  return fillers.value.filter((filler) => !term || filler.product_name.toLowerCase().includes(term));
});
// selectedFlowers holds every placed model; fillers are marked kind: "filler".
const placedFlowers = computed(() => selectedFlowers.value.filter((item) => item.kind !== "filler"));
const placedFillers = computed(() => selectedFlowers.value.filter((item) => item.kind === "filler"));
const findWrapper = (id) => wrapperOptions.value.find((option) => option.id === id) || null;
const selectedOuterWrapper = computed(() => findWrapper(wrap.outer.id));
const selectedInnerWrapper = computed(() => findWrapper(wrap.inner.id));
const activeWrap = computed(() => wrap[activeWrapLayer.value]);
const selectedRibbon = computed(() => ribbonOptions.value.find((option) => option.id === selectedRibbonId.value) || null);
const extrasPrice = computed(
  () => Number(selectedOuterWrapper.value?.price || 0) + Number(selectedInnerWrapper.value?.price || 0) + Number(selectedRibbon.value?.price || 0),
);
const totalPrice = computed(() => selectedFlowers.value.reduce((sum, flower) => sum + flower.price, 0) + extrasPrice.value);
const selectedFlower = computed(() => selectedFlowers.value.find((flower) => flower.sceneId === selectedSceneId.value) || null);
const flowersSubtotal = computed(() => placedFlowers.value.reduce((sum, flower) => sum + flower.price, 0));
const fillersSubtotal = computed(() => placedFillers.value.reduce((sum, filler) => sum + filler.price, 0));
const stemSlots = computed(() => Array.from({ length: MAX_FLOWERS }, (_, index) => placedFlowers.value[index] || null));
const flowerImage = (flowerId) => flowers.value.find((flower) => flower.id === flowerId)?.image || null;
const rangeFill = (value, min, max) => ({ "--fill": `${((Number(value) - min) / (max - min)) * 100}%` });
const stageHint = computed(() => {
  if (pendingFlower.value) return { title: `Click the wrap to place ${pendingFlower.value.product_name}`, detail: "A marker shows where it will land." };
  if (selectedFlower.value) {
    return selectedFlower.value.locked
      ? { title: `${selectedFlower.value.product_name} is locked`, detail: "Unlock it in Adjust to move it again." }
      : { title: `Drag ${selectedFlower.value.product_name} to move it`, detail: "Drag the background to rotate the view." };
  }
  if (placedFlowers.value.length >= MAX_FLOWERS) return { title: "All stems placed", detail: "Adjust them or choose a wrapper and ribbon." };
  return { title: "Pick a flower, then click the wrap", detail: "Drag to rotate the view, scroll to zoom." };
});

let scene, camera, renderer, controls, bouquetRoot, flowerLayer, hoverMarker, animationFrameId = 0, pointerDown = null, transformDrag = null, toastTimer;
const loader = new GLTFLoader();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const bouquetTargets = [];
let dressing = null;
const modelCache = new Map();
const placedObjects = new Map();

const isFiller = (item) => item?.kind === "filler";
const countOfFlower = (flowerId, kind = "flower") =>
  selectedFlowers.value.filter((flower) => flower.id === flowerId && (flower.kind || "flower") === kind).length;
const canUseFlower = (flower) => {
  if (!flower?.model) return false;
  if (isFiller(flower)) return placedFillers.value.length < MAX_FILLERS;
  return placedFlowers.value.length < MAX_FLOWERS && flower.quantity_in_stock > countOfFlower(flower.id);
};

onMounted(async () => {
  if (isAuthenticated.value) await cartStore.initialize?.();
  initScene();
  await loadEditingCustomization();
  await Promise.all([loadBouquetModel(), fetchFlowers(), fetchCustomizationOptions()]);
  if (editingCustomization.value) await restoreBouquet(editingCustomization.value);
  window.addEventListener("resize", handleResize);
  document.addEventListener("fullscreenchange", syncFullscreenState);
});

onUnmounted(() => {
  window.removeEventListener("resize", handleResize);
  document.removeEventListener("fullscreenchange", syncFullscreenState);
  cleanupScene();
  clearTimeout(toastTimer);
});

function initScene() {
  if (!viewerContainer.value) return;
  scene = new THREE.Scene();
  scene.background = new THREE.Color("#ece6dd");
  camera = new THREE.PerspectiveCamera(42, viewerContainer.value.clientWidth / viewerContainer.value.clientHeight, 0.1, 100);
  camera.position.set(0, 2.1, 6.4);
  renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.shadowMap.enabled = true;
  viewerContainer.value.appendChild(renderer.domElement);
  controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.target.set(0, 1.5, 0);
  controls.minDistance = 3.8;
  controls.maxDistance = 9;
  controls.maxPolarAngle = Math.PI * 0.48;
  const ambient = new THREE.AmbientLight(0xffffff, 1.2);
  const key = new THREE.DirectionalLight(0xfff1dc, 1.6);
  key.position.set(4, 8, 5);
  key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048);
  const fill = new THREE.DirectionalLight(0xe2f0ff, 0.7);
  fill.position.set(-4, 3, -2);
  const ground = new THREE.Mesh(new THREE.CircleGeometry(10, 64), new THREE.ShadowMaterial({ opacity: 0.12 }));
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.02;
  ground.receiveShadow = true;
  hoverMarker = new THREE.Mesh(new THREE.SphereGeometry(0.08, 16, 16), new THREE.MeshStandardMaterial({ color: 0xb86558, emissive: 0xb86558, emissiveIntensity: 0.35 }));
  hoverMarker.visible = false;
  flowerLayer = new THREE.Group();
  scene.add(ambient, key, fill, ground, flowerLayer, hoverMarker);
  setupCanvasInteractions();
  animateScene();
}

async function loadBouquetModel() {
  isLoading3D.value = true;
  bouquetLoadError.value = "";
  try {
    const gltf = await loadFirstAvailableGltf(["/bouquet.glb", "/Boquet.glb"]);
    bouquetRoot = gltf.scene || gltf.scenes?.[0];
    if (!bouquetRoot) throw new Error("No scene found inside bouquet.glb.");

    bouquetRoot.traverse((child) => {
      if (!child.isMesh) return;
      child.castShadow = true;
      child.receiveShadow = true;
      bouquetTargets.push(child);
    });

    const box = new THREE.Box3().setFromObject(bouquetRoot);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    const scale = 3.5 / Math.max(size.x, size.y, size.z, 1);
    bouquetRoot.scale.setScalar(scale);
    bouquetRoot.position.sub(center.multiplyScalar(scale));
    const scaledBox = new THREE.Box3().setFromObject(bouquetRoot);
    bouquetRoot.position.y -= scaledBox.min.y;
    bouquetRoot.scale.multiplyScalar(wrapperScale.value);
    scene.add(bouquetRoot);
    // Only the two paper layers are tinted; the ribbon is generated.
    dressing = dressBouquet(bouquetRoot);
    dressing.outer.apply(wrap.outer.color);
    dressing.inner.apply(wrap.inner.color);
    dressing.applyRibbon(ribbonColor.value);
    sceneReady.value = true;
  } catch (error) {
    console.error("Failed to load bouquet base model:", error);
    bouquetLoadError.value = "Make sure the bouquet base model exists in `frontend/public` as `/bouquet.glb` or `/Boquet.glb`.";
  } finally {
    isLoading3D.value = false;
  }
}

async function fetchFlowers() {
  if (!storeId.value) return;
  loadingFlowers.value = true;
  try {
    const response = await api.get(`/stores/${storeId.value}/customizable-flowers`, {
      params: vendorOwnerId.value ? { owner_id: vendorOwnerId.value } : {},
    });
    const rawItems = Array.isArray(response.data?.data) ? response.data.data : [];
    flowers.value = rawItems
      .map(normalizeFlower)
      .filter((flower) => flower.selling_type === "per_piece_customizable" && !!flower.model);
  } catch (error) {
    console.error("Failed to fetch customizable flowers:", error);
    flowers.value = [];
    showToast("Failed to load vendor flowers.", "error");
  } finally {
    loadingFlowers.value = false;
  }
}

// The vendor's wrappers, ribbons and fillers from the 3D Customization page.
async function fetchCustomizationOptions() {
  if (!storeId.value) return;
  try {
    const response = await api.get(`/stores/${storeId.value}/customization-options`, {
      params: vendorOwnerId.value ? { owner_id: vendorOwnerId.value } : {},
    });
    const data = response.data?.data || {};
    const toOption = (option) => ({ ...option, id: Number(option.id), price: Number(option.price || 0) });
    wrapperOptions.value = (data.wrappers || []).map(toOption);
    ribbonOptions.value = (data.ribbons || []).map(toOption);
    fillers.value = (data.fillers || []).filter((filler) => filler.model_url).map((filler) => ({
      kind: "filler",
      id: Number(filler.id),
      product_name: filler.name,
      description: filler.description || "",
      price: Number(filler.price || 0),
      model: filler.model_url,
      model_3d_url: filler.model_url,
    }));

    // Wrappers start on the bouquet's original paper unless a saved bouquet chose one.
    const outerWrapper = findWrapper(Number(editingCustomization.value?.wrapper?.id || 0));
    const innerWrapper = findWrapper(Number(editingCustomization.value?.inner_wrapper?.id || 0));
    if (outerWrapper) selectWrapper("outer", outerWrapper);
    if (innerWrapper) selectWrapper("inner", innerWrapper);
    const savedRibbonId = Number(editingCustomization.value?.ribbon?.id || 0);
    const ribbon = ribbonOptions.value.find((option) => option.id === savedRibbonId) || ribbonOptions.value[0];
    if (ribbon) selectRibbon(ribbon);
  } catch (error) {
    // Fall back to the built-in colours if the vendor's items cannot be loaded.
    console.error("Failed to fetch customization options:", error);
  }
}

// option null = the bouquet's original paper
function selectWrapper(layer, option) {
  wrap[layer].id = option?.id ?? null;
  applyWrapColor(layer, option?.color_hex || null);
}

function applyWrapColor(layer, color) {
  wrap[layer].color = color || null;
  dressing?.[layer].apply(wrap[layer].color);
}

function pickWrapPreset(color) {
  wrap[activeWrapLayer.value].id = null;
  applyWrapColor(activeWrapLayer.value, color);
}

function selectRibbon(option) {
  selectedRibbonId.value = option.id;
  applyRibbonColor(option.color_hex);
}

const formatPeso = (value) => `PHP ${Number(value || 0).toFixed(2)}`;

function normalizeFlower(flower) {
  const model = resolveModelUrl(flower);
  const sellingType = flower.selling_type || "per_piece";

  const isCustomizable = sellingType === "per_piece_customizable";

  return {
    id: Number(flower.id),
    owner_id: Number(flower.owner_id || vendorOwnerId.value || 0) || null,
    product_name: flower.product_name || flower.name || "Unnamed Flower",
    price: Number(flower.price ?? flower.selling_price ?? 0),
    quantity_in_stock: Number(flower.quantity_in_stock ?? flower.stock ?? 0),
    selling_type: sellingType,
    is_customizable: isCustomizable,
    image:
      flower.primary_image_url ||
      flower.primary_image?.image_url ||
      flower.images?.[0]?.image_url ||
      null,
    model,
    model_3d_url: model,
  };
}

function resolveModelUrl(flower) {
  const modelRecord = Array.isArray(flower.models) ? flower.models[0] : null;
  const rawModel = modelRecord?.model_url || modelRecord?.model_path || flower.model_3d_url || null;
  if (!rawModel || typeof rawModel !== "string") return null;
  if (/^https?:\/\//i.test(rawModel)) return rawModel.replace(/^http:\/\//i, "https://");
  if (rawModel.startsWith("/")) return `${window.location.origin}${rawModel}`;
  return `https://res.cloudinary.com/dqs1e1inx/raw/upload/${rawModel.replace(/^\/+/, "")}`;
}

function selectFlower(flower) {
  if (!canUseFlower(flower)) return enforceFlowerLimit(flower);
  pendingFlower.value = flower;
  showToast(`Click the bouquet to place ${flower.product_name}.`, "success");
}

function onDragStart(event, flower) {
  if (!canUseFlower(flower)) {
    event.preventDefault();
    return enforceFlowerLimit(flower);
  }
  draggedFlower.value = {
    kind: flower.kind || "flower",
    id: flower.id,
    name: flower.product_name,
    product_name: flower.product_name,
    price: flower.price,
    model: flower.model,
    quantity_in_stock: flower.quantity_in_stock,
    owner_id: flower.owner_id,
    selling_type: flower.selling_type,
    is_customizable: flower.is_customizable,
  };
  dragFeedback.value = `Drop ${flower.product_name} on the bouquet`;
  event.dataTransfer.effectAllowed = "copy";
}

function onDragEnd() {
  draggedFlower.value = null;
  dragFeedback.value = "";
  isDragOver.value = false;
  hideHoverMarker();
}

function onCanvasDragOver(event) {
  isDragOver.value = true;
  if (!draggedFlower.value) return;
  dragFeedback.value = `Release to place ${draggedFlower.value.product_name}`;
  updateHoverMarker(event.clientX, event.clientY);
}

function onCanvasDragLeave() {
  isDragOver.value = false;
  dragFeedback.value = draggedFlower.value ? `Drop ${draggedFlower.value.product_name} on the bouquet` : "";
  hideHoverMarker();
}

async function onCanvasDrop(event) {
  isDragOver.value = false;
  dragFeedback.value = "";
  if (!draggedFlower.value) return;
  const placement = getPlacement(event.clientX, event.clientY);
  if (!placement) {
    draggedFlower.value = null;
    hideHoverMarker();
    return showToast("Drop directly on the bouquet.", "error");
  }
  await placeFlowerOnBouquet(draggedFlower.value, placement);
  draggedFlower.value = null;
  hideHoverMarker();
}

function setupCanvasInteractions() {
  const canvas = renderer.domElement;
  canvas.addEventListener("pointerdown", (event) => {
    if (event.button !== 0) return;
    pointerDown = { x: event.clientX, y: event.clientY };
    const placedHit = getPlacedFlowerHit(event.clientX, event.clientY);
    if (placedHit?.sceneId) {
      event.preventDefault();
      selectPlacedFlower(placedHit.sceneId);
      transformDrag = { sceneId: placedHit.sceneId, pointerId: event.pointerId };
      isTransformDragging.value = true;
      if (canvas.setPointerCapture) {
        canvas.setPointerCapture(event.pointerId);
      }
      controls.enabled = false;
      return;
    }

    if (
      selectedSceneId.value &&
      !pendingFlower.value &&
      !draggedFlower.value &&
      getPlacement(event.clientX, event.clientY)
    ) {
      event.preventDefault();
      transformDrag = { sceneId: selectedSceneId.value, pointerId: event.pointerId };
      isTransformDragging.value = true;
      if (canvas.setPointerCapture) {
        canvas.setPointerCapture(event.pointerId);
      }
      controls.enabled = false;
    }
  });
  canvas.addEventListener("pointermove", (event) => {
    if (transformDrag?.sceneId) {
      event.preventDefault();
      moveSelectedFlower(event.clientX, event.clientY, transformDrag.sceneId);
      return;
    }
    if (pendingFlower.value || draggedFlower.value) updateHoverMarker(event.clientX, event.clientY);
  });
  canvas.addEventListener("pointerleave", () => {
    endTransformDrag();
    hideHoverMarker();
  });
  canvas.addEventListener("pointerup", async (event) => {
    if (!pointerDown || event.button !== 0) return;
    const moved = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y) > 6;
    if (transformDrag?.sceneId) {
      endTransformDrag(event.pointerId);
      pointerDown = null;
      return;
    }
    const placedHit = getPlacedFlowerHit(event.clientX, event.clientY);
    if (!moved && placedHit?.sceneId) {
      selectPlacedFlower(placedHit.sceneId);
      pointerDown = null;
      return;
    }
    pointerDown = null;
    if (moved || !pendingFlower.value) return;
    const placement = getPlacement(event.clientX, event.clientY);
    if (!placement) return showToast("Click directly on the bouquet to place the flower.", "error");
    await placeFlowerOnBouquet(pendingFlower.value, placement);
    pendingFlower.value = null;
    hideHoverMarker();
  });
  canvas.addEventListener("pointercancel", (event) => {
    endTransformDrag(event.pointerId);
    pointerDown = null;
  });
}

function getPlacement(clientX, clientY) {
  if (!viewerContainer.value || !camera || !bouquetTargets.length) return null;
  const bounds = viewerContainer.value.getBoundingClientRect();
  pointer.x = ((clientX - bounds.left) / bounds.width) * 2 - 1;
  pointer.y = -((clientY - bounds.top) / bounds.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const intersections = raycaster.intersectObjects(bouquetTargets, true);
  if (!intersections.length) return null;
  const hit = intersections[0];
  const normal = hit.face?.normal ? hit.face.normal.clone().transformDirection(hit.object.matrixWorld).normalize() : new THREE.Vector3(0, 1, 0);
  return { point: hit.point.clone().add(normal.clone().multiplyScalar(0.05)), normal };
}

function getPlacedFlowerHit(clientX, clientY) {
  if (!viewerContainer.value || !camera || !placedObjects.size) return null;
  const bounds = viewerContainer.value.getBoundingClientRect();
  pointer.x = ((clientX - bounds.left) / bounds.width) * 2 - 1;
  pointer.y = -((clientY - bounds.top) / bounds.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const intersections = raycaster.intersectObjects(Array.from(placedObjects.values()), true);
  if (!intersections.length) return null;
  let current = intersections[0].object;
  while (current && !current.userData?.sceneId) current = current.parent;
  return current?.userData?.sceneId ? { sceneId: current.userData.sceneId, object: current } : null;
}

function updateHoverMarker(clientX, clientY) {
  const placement = getPlacement(clientX, clientY);
  if (!placement) return hideHoverMarker();
  hoverMarker.visible = true;
  hoverMarker.position.copy(placement.point);
}

function hideHoverMarker() {
  if (hoverMarker) hoverMarker.visible = false;
}

function endTransformDrag(pointerId = null) {
  const canvas = renderer?.domElement;
  if (canvas && pointerId !== null && canvas.hasPointerCapture?.(pointerId)) {
    canvas.releasePointerCapture(pointerId);
  }
  transformDrag = null;
  isTransformDragging.value = false;
  if (controls) controls.enabled = true;
}

async function placeFlowerOnBouquet(flower, placement) {
  const filler = isFiller(flower);
  if (filler && placedFillers.value.length >= MAX_FILLERS) {
    return showToast(`Maximum of ${MAX_FILLERS} fillers only.`, "error");
  }
  if (!filler && placedFlowers.value.length >= MAX_FLOWERS) {
    window.alert("Maximum of 3 flowers only");
    showToast("Maximum of 3 flowers only.", "error");
    return;
  }
  if (!filler && countOfFlower(flower.id) >= Number(flower.quantity_in_stock || 0)) {
    return showToast("This flower is out of stock.", "error");
  }
  if (!flower.model) {
    return showToast(`This ${filler ? "filler" : "flower"} has no 3D model.`, "error");
  }

  try {
    const template = await loadFlowerTemplate(flower.model);
    const instance = SkeletonUtils.clone(template);
    const wrapper = new THREE.Group();
    const sceneId = `${filler ? "filler-" : ""}${flower.id}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const initialRotation = {
      xDeg: -5,
      yDeg: 180,
      zDeg: THREE.MathUtils.radToDeg((Math.random() - 0.5) * 0.14),
    };
    fitFlowerModel(instance);
    wrapper.add(instance);
    wrapper.position.copy(placement.point);
    wrapper.rotation.set(
      THREE.MathUtils.degToRad(initialRotation.xDeg),
      THREE.MathUtils.degToRad(initialRotation.yDeg),
      THREE.MathUtils.degToRad(initialRotation.zDeg),
    );
    wrapper.userData.sceneId = sceneId;
    flowerLayer.add(wrapper);
    placedObjects.set(sceneId, wrapper);
    selectedFlowers.value.push({
      sceneId,
      kind: filler ? "filler" : "flower",
      id: flower.id,
      owner_id: flower.owner_id,
      product_name: flower.product_name || flower.name,
      price: Number(flower.price || 0),
      model_3d_url: flower.model,
      position: { x: placement.point.x, y: placement.point.y, z: placement.point.z },
      rotation: initialRotation,
      scale: 1,
      locked: false,
    });
    selectedSceneId.value = sceneId;
    showToast(`${flower.product_name} placed on bouquet.`, "success");
  } catch (error) {
    console.error("Failed to place flower model:", error);
    showToast(`Failed to load the ${filler ? "filler" : "flower"} 3D model.`, "error");
  }
}

async function loadFlowerTemplate(url) {
  if (modelCache.has(url)) return modelCache.get(url);
  const gltf = await loadGltf(url);
  const template = gltf.scene || gltf.scenes?.[0];
  if (!template) throw new Error(`No scene found in flower model: ${url}`);
  template.traverse((child) => {
    if (!child.isMesh) return;
    child.castShadow = true;
    child.receiveShadow = true;
  });
  modelCache.set(url, template);
  return template;
}

function fitFlowerModel(model) {
  const sourceBox = new THREE.Box3().setFromObject(model);
  const size = sourceBox.getSize(new THREE.Vector3());
  const scale = 0.9 / Math.max(size.x, size.y, size.z, 1);
  model.scale.setScalar(scale);
  model.updateMatrixWorld(true);
  const scaledBox = new THREE.Box3().setFromObject(model);
  const center = scaledBox.getCenter(new THREE.Vector3());
  model.position.sub(center);
  model.updateMatrixWorld(true);
}



function applyRibbonColor(color) {
  ribbonColor.value = color;
  dressing?.applyRibbon(color);
}

function removeFlower(sceneId) {
  selectedFlowers.value = selectedFlowers.value.filter((flower) => flower.sceneId !== sceneId);
  if (selectedSceneId.value === sceneId) {
    selectedSceneId.value = selectedFlowers.value[0]?.sceneId ?? null;
  }
  const object = placedObjects.get(sceneId);
  if (object) {
    flowerLayer.remove(object);
    placedObjects.delete(sceneId);
  }
}

function resetDesign() {
  selectedFlowers.value.forEach((flower) => {
    const object = placedObjects.get(flower.sceneId);
    if (object) flowerLayer.remove(object);
  });
  selectedFlowers.value = [];
  selectedSceneId.value = null;
  placedObjects.clear();
  pendingFlower.value = null;
  draggedFlower.value = null;
  hideHoverMarker();
}

function selectPlacedFlower(sceneId) {
  selectedSceneId.value = sceneId;
}

function moveSelectedFlower(clientX, clientY, sceneId = selectedSceneId.value) {
  const placement = getPlacement(clientX, clientY);
  const object = sceneId ? placedObjects.get(sceneId) : null;
  const flower = selectedFlowers.value.find((entry) => entry.sceneId === sceneId);
  if (!placement || !object || flower?.locked) return;
  object.position.copy(placement.point);
  updateFlowerState(sceneId, {
    position: { x: placement.point.x, y: placement.point.y, z: placement.point.z },
  });
}

function updateSelectedRotation(axis, degrees) {
  const sceneId = selectedSceneId.value;
  const object = sceneId ? placedObjects.get(sceneId) : null;
  const flower = selectedFlower.value;
  if (!sceneId || !object || !flower) return;
  const nextRotation = { ...flower.rotation, [`${axis}Deg`]: degrees };
  if (axis === "x") object.rotation.x = THREE.MathUtils.degToRad(degrees);
  if (axis === "y") object.rotation.y = THREE.MathUtils.degToRad(degrees);
  updateFlowerState(sceneId, { rotation: nextRotation });
}

function updateSelectedScale(scale) {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object) return;

  const nextScale = THREE.MathUtils.clamp(scale, 0.25, 2);
  object.scale.setScalar(nextScale);
  updateFlowerState(flower.sceneId, { scale: nextScale });
}

function nudgeSelectedRotation(axis, delta) {
  const flower = selectedFlower.value;
  if (!flower) return;
  updateSelectedRotation(axis, flower.rotation[`${axis}Deg`] + delta);
}

function resetSelectedTransform() {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object) return;
  const resetRotation = { xDeg: -5, yDeg: 180, zDeg: 0 };
  object.rotation.set(
    THREE.MathUtils.degToRad(resetRotation.xDeg),
    THREE.MathUtils.degToRad(resetRotation.yDeg),
    THREE.MathUtils.degToRad(resetRotation.zDeg),
  );
  object.scale.setScalar(1);
  updateFlowerState(flower.sceneId, { rotation: resetRotation, scale: 1 });
}

function toggleSelectedLock() {
  const flower = selectedFlower.value;
  if (!flower) return;
  updateFlowerState(flower.sceneId, { locked: !flower.locked });
}

function toggleFlowerLock(sceneId) {
  const flower = selectedFlowers.value.find((entry) => entry.sceneId === sceneId);
  if (!flower) return;
  updateFlowerState(sceneId, { locked: !flower.locked });
}

function nudgeSelectedPosition(direction) {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object || flower.locked) return;

  const step = 0.08;
  const nextPosition = {
    x: flower.position.x,
    y: flower.position.y,
    z: flower.position.z,
  };

  if (direction === "left") nextPosition.x -= step;
  if (direction === "right") nextPosition.x += step;
  if (direction === "up") nextPosition.y += step;
  if (direction === "down") nextPosition.y -= step;

  object.position.set(nextPosition.x, nextPosition.y, nextPosition.z);
  updateFlowerState(flower.sceneId, { position: nextPosition });
}

function updateFlowerState(sceneId, patch) {
  selectedFlowers.value = selectedFlowers.value.map((flower) =>
    flower.sceneId === sceneId ? { ...flower, ...patch } : flower,
  );
}

function enforceFlowerLimit(flower) {
  if (isFiller(flower)) {
    return showToast(`Maximum of ${MAX_FILLERS} fillers only.`, "error");
  }
  if (placedFlowers.value.length >= MAX_FLOWERS) {
    window.alert("Maximum of 3 flowers only");
    return showToast("Maximum of 3 flowers only.", "error");
  }
  if (flower.quantity_in_stock <= countOfFlower(flower.id)) {
    return showToast(`${flower.product_name} is out of stock.`, "error");
  }
  if (!flower.model) {
    return showToast(`${flower.product_name} has no 3D model.`, "error");
  }
}

async function checkout() {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  if (!placedFlowers.value.length) return;
  isCheckingOut.value = true;
  try {
    const customization = buildBouquetCustomization();
    sessionStorage.setItem(
      "directCheckout",
      JSON.stringify({
        items: placedFlowers.value.map((flower) => ({
          product_id: flower.id,
          quantity: 1,
          product_name: flower.product_name,
          price: flower.price,
        })),
        custom_items: placedFlowers.value.map((flower) => ({
          product_id: flower.id,
          quantity: 1,
          vendor_id: flower.owner_id,
          product_name: flower.product_name,
          price: flower.price,
          customizations: customization,
        })),
        total: totalPrice.value,
        isDirectCheckout: true,
      }),
    );
    await router.push({
      path: "/customer/checkout",
      query: { direct: "true", custom: "flower_bouquet" },
    });
  } catch (error) {
    showToast(error?.response?.data?.message || "Failed to start checkout.", "error");
  } finally {
    isCheckingOut.value = false;
  }
}

function buildBouquetCustomization() {
  return {
    type: "custom_flower_bouquet",
    bouquet_model_url: "/Boquet.glb",
    store_id: storeId.value,
    vendor_id: vendorOwnerId.value,
    paper_color: wrap.outer.color,
    inner_paper_color: wrap.inner.color,
    ribbon_color: ribbonColor.value,
    wrapper_scale: wrapperScale.value,
    // Prices for these are looked up by id on the server.
    wrapper: selectedOuterWrapper.value ? { id: selectedOuterWrapper.value.id, name: selectedOuterWrapper.value.name } : null,
    inner_wrapper: selectedInnerWrapper.value ? { id: selectedInnerWrapper.value.id, name: selectedInnerWrapper.value.name } : null,
    ribbon: selectedRibbon.value ? { id: selectedRibbon.value.id, name: selectedRibbon.value.name } : null,
    fillers: placedFillers.value.map((filler, index) => ({
      scene_id: filler.sceneId,
      id: filler.id,
      name: filler.product_name,
      model_3d_url: filler.model_3d_url,
      price: Number(filler.price || 0),
      placement_order: index,
      position: { ...filler.position },
      rotation: { ...filler.rotation },
      scale: Number(filler.scale ?? 1),
      locked: filler.locked,
    })),
    flowers: placedFlowers.value.map((flower, index) => {
      const object = placedObjects.get(flower.sceneId);

      return {
        scene_id: flower.sceneId,
        product_id: flower.id,
        product_name: flower.product_name,
        model_3d_url: flower.model_3d_url,
        price: Number(flower.price || 0),
        quantity: 1,
        placement_order: index,
        position: { ...flower.position },
        rotation: { ...flower.rotation },
        scale: Number(flower.scale ?? object?.scale.x ?? 1),
        locked: flower.locked,
      };
    }),
  };
}

async function addCart() {
  if (!isAuthenticated.value) {
    router.push({ path: "/guest/login", query: { redirect: route.fullPath } });
    return;
  }
  if (!placedFlowers.value.length) return;

  isCheckingOut.value = true;
  try {
    const customization = buildBouquetCustomization();
    const payload = {
      product_id: placedFlowers.value[0].id,
      quantity: 1,
      customizations: customization,
    };

    if (editingCartItemId.value) {
      await cartStore.updateCartItem(editingCartItemId.value, payload);
      toastify.success("Custom bouquet updated in cart.");
    } else {
      await cartStore.addToCart(payload);
      toastify.success("Custom bouquet added to cart.");
    }
  } catch (error) {
    console.error("Failed to add custom bouquet to cart:", error);
    showToast(error?.response?.data?.message || error?.message || "Failed to add custom bouquet to cart.", "error");
  } finally {
    isCheckingOut.value = false;
  }
}

async function loadEditingCustomization() {
  if (!editingCartItemId.value || !isAuthenticated.value) return;
  await cartStore.refreshCart();
  const cartItem = cartStore.items.value.find((item) => String(item.id) === editingCartItemId.value);
  const customization = cartItem?.customizations;
  if (customization?.type !== "custom_flower_bouquet" || !Array.isArray(customization.flowers)) {
    showToast("The saved custom bouquet could not be found.", "error");
    return;
  }
  editingCustomization.value = customization;
  wrapperScale.value = Number(customization.wrapper_scale ?? 1);
}

async function restoreBouquet(customization) {
  resetDesign();
  const byOrder = (first, second) => Number(first.placement_order || 0) - Number(second.placement_order || 0);
  const savedFlowers = [
    ...[...customization.flowers].sort(byOrder),
    ...[...(Array.isArray(customization.fillers) ? customization.fillers : [])]
      .sort(byOrder)
      .map((filler) => ({ ...filler, kind: "filler", product_id: filler.id, product_name: filler.name })),
  ];
  applyWrapColor("outer", selectedOuterWrapper.value?.color_hex || customization.paper_color || null);
  applyWrapColor("inner", selectedInnerWrapper.value?.color_hex || customization.inner_paper_color || null);
  applyRibbonColor(selectedRibbon.value?.color_hex || customization.ribbon_color || ribbonColor.value);

  for (const [index, savedFlower] of savedFlowers.entries()) {
    if (!savedFlower.model_3d_url) continue;
    try {
      const template = await loadFlowerTemplate(savedFlower.model_3d_url);
      const instance = SkeletonUtils.clone(template);
      fitFlowerModel(instance);
      const wrapper = new THREE.Group();
      const sceneId = savedFlower.scene_id || `restored-${savedFlower.product_id}-${index}`;
      const position = savedFlower.position || { x: 0, y: 0, z: 0 };
      const rotation = savedFlower.rotation || { xDeg: 0, yDeg: 0, zDeg: 0 };
      const scale = Number(savedFlower.scale ?? 1);
      wrapper.add(instance);
      wrapper.position.set(Number(position.x || 0), Number(position.y || 0), Number(position.z || 0));
      wrapper.rotation.set(
        THREE.MathUtils.degToRad(Number(rotation.xDeg || 0)),
        THREE.MathUtils.degToRad(Number(rotation.yDeg || 0)),
        THREE.MathUtils.degToRad(Number(rotation.zDeg || 0)),
      );
      wrapper.scale.setScalar(scale);
      wrapper.userData.sceneId = sceneId;
      flowerLayer.add(wrapper);
      placedObjects.set(sceneId, wrapper);
      const filler = isFiller(savedFlower);
      const catalogFlower = (filler ? fillers.value : flowers.value).find(
        (flower) => Number(flower.id) === Number(savedFlower.product_id),
      );
      selectedFlowers.value.push({
        sceneId,
        kind: filler ? "filler" : "flower",
        id: Number(savedFlower.product_id),
        owner_id: Number(savedFlower.owner_id || catalogFlower?.owner_id || vendorOwnerId.value || 0) || null,
        product_name: savedFlower.product_name || catalogFlower?.product_name || (filler ? "Filler" : "Flower"),
        price: Number(savedFlower.price ?? catalogFlower?.price ?? 0),
        model_3d_url: savedFlower.model_3d_url,
        position: { x: Number(position.x || 0), y: Number(position.y || 0), z: Number(position.z || 0) },
        rotation: { xDeg: Number(rotation.xDeg || 0), yDeg: Number(rotation.yDeg || 0), zDeg: Number(rotation.zDeg || 0) },
        scale,
        locked: Boolean(savedFlower.locked),
      });
    } catch (error) {
      console.error("Failed to restore saved flower:", error);
    }
  }
  selectedSceneId.value = selectedFlowers.value[0]?.sceneId || null;
}

function takeScreenshot() {
  if (!renderer) return;
  renderer.render(scene, camera);
  const link = document.createElement("a");
  link.href = renderer.domElement.toDataURL("image/png");
  link.download = "flower-customizer.png";
  link.click();
}

function toggleLeftSidebar() {
  isLeftSidebarCollapsed.value = !isLeftSidebarCollapsed.value;
  queueResize();
}

function toggleRightSidebar() {
  isRightSidebarCollapsed.value = !isRightSidebarCollapsed.value;
  queueResize();
}

async function toggleFullscreen() {
  const layout = layoutContainer.value;
  if (!layout) return;

  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    } else {
      await layout.requestFullscreen();
    }
  } catch (error) {
    console.error("Failed to toggle fullscreen:", error);
    showToast("Fullscreen is not available.", "error");
  }
}

function syncFullscreenState() {
  isFullscreen.value = !!document.fullscreenElement;
  queueResize();
}

function queueResize() {
  window.requestAnimationFrame(() => {
    handleResize();
  });
}

function animateScene() {
  animationFrameId = window.requestAnimationFrame(animateScene);
  controls?.update();
  renderer?.render(scene, camera);
}

function handleResize() {
  if (!viewerContainer.value || !camera || !renderer) return;
  camera.aspect = viewerContainer.value.clientWidth / viewerContainer.value.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
}

function cleanupScene() {
  window.cancelAnimationFrame(animationFrameId);
  controls?.dispose();
  renderer?.dispose();
  if (renderer?.domElement && viewerContainer.value?.contains(renderer.domElement)) {
    viewerContainer.value.removeChild(renderer.domElement);
  }
}

function loadGltf(url) {
  return new Promise((resolve, reject) => loader.load(url, resolve, undefined, reject));
}

async function loadFirstAvailableGltf(urls) {
  let lastError = null;
  for (const url of urls) {
    try {
      return await loadGltf(url);
    } catch (error) {
      lastError = error;
    }
  }
  throw lastError || new Error("Unable to load bouquet model.");
}

function showToast(message, type = "success") {
  toast.show = true;
  toast.message = message;
  toast.type = type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    toast.show = false;
  }, 2800);
}
</script>

<style scoped>
/* Tokens, type, buttons and skeletons come from src/styles/bloomcraft.css,
   the same system Shop.vue uses. Only customizer layout lives here. */

@layer bc-reset {
  .fc-page :is(p, h1, h2, h3, ul, ol, dl, dd) {
    margin: 0;
  }

  .fc-page :is(ul, ol) {
    padding: 0;
    list-style: none;
  }
}

:global(body) {
  margin: 0;
}

.fc-page {
  /* Matches the Three.js scene background so the canvas sits flush */
  --stage: #ece6dd;
  --radius-lg: 22px;
  --radius-md: 14px;
  --radius-sm: 10px;

  min-height: 100vh;
  background: var(--ivory);
  overflow-x: hidden;
}

.fc-page button,
.fc-page input {
  font-family: var(--font-sans);
  color: inherit;
}

.fc-page :is(button, [role="button"], input, label):focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 2px;
}

.fc-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fc-shell {
  max-width: 1560px;
  margin: 0 auto;
  padding: 116px clamp(16px, 2.4vw, 32px) 32px;
}

/* ============================================================
   HEADER
============================================================= */
.fc-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
  padding-bottom: 24px;
  margin-bottom: 24px;
  border-bottom: 1px solid var(--line);
}

.fc-store {
  color: var(--rosewood-dark);
  font-size: 13.5px;
  font-weight: 500;
}

.fc-head h1 {
  margin: 6px 0 10px;
  font-size: clamp(40px, 4.4vw, 60px);
}

.fc-lede {
  max-width: 520px;
  color: var(--ink-soft);
  font-size: 15px;
  line-height: 1.7;
}

.fc-head-meta {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-shrink: 0;
}

/* Stem slots echo the arched frame of the shop hero */
.fc-stems {
  display: flex;
  gap: 8px;
}

.fc-stem {
  width: 46px;
  height: 58px;
  border-radius: 999px 999px 8px 8px;
  overflow: hidden;
}

.fc-stem-empty,
.fc-stem button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
}

.fc-stem-empty {
  border: 1px dashed rgba(30, 27, 24, 0.22);
  border-radius: inherit;
  color: rgba(30, 27, 24, 0.28);
}

.fc-stem-empty .fc-icon {
  width: 14px;
  height: 14px;
}

.fc-stem button {
  padding: 0;
  border: none;
  border-radius: inherit;
  background: var(--ivory-deep);
  color: var(--rosewood-dark);
  cursor: pointer;
}

.fc-stem img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.fc-stem.active button {
  box-shadow: inset 0 0 0 2px var(--ink);
}

.fc-head-total {
  display: grid;
  gap: 2px;
  padding-left: 24px;
  border-left: 1px solid var(--line);
  text-align: right;
}

.fc-head-total span {
  color: var(--ink-soft);
  font-size: 12.5px;
}

.fc-head-total strong {
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 500;
  line-height: 1;
  font-variant-numeric: lining-nums tabular-nums;
}

/* ============================================================
   LAYOUT
============================================================= */
.fc-layout {
  display: grid;
  grid-template-columns: 312px minmax(0, 1fr) 348px;
  gap: 16px;
  height: calc(100vh - 140px);
  min-height: 640px;
}

.fc-layout.left-collapsed {
  grid-template-columns: minmax(0, 1fr) 348px;
}

.fc-layout.right-collapsed {
  grid-template-columns: 312px minmax(0, 1fr);
}

.fc-layout.left-collapsed.right-collapsed {
  grid-template-columns: minmax(0, 1fr);
}

.fc-layout:fullscreen,
.fc-layout.fullscreen {
  width: 100vw;
  height: 100vh;
  padding: 16px;
  background: var(--ivory);
}

.fc-layout:-webkit-full-screen {
  width: 100vw;
  height: 100vh;
  padding: 16px;
  background: var(--ivory);
}

.fc-panel {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--paper);
  border: 1px solid var(--line-soft);
  border-radius: var(--radius-lg);
}

.fc-panel-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.fc-panel-head h2 {
  font-size: 24px;
  white-space: nowrap;
}

.fc-panel-head > span {
  min-width: 0;
  color: var(--ink-soft);
  font-size: 12.5px;
}

.fc-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-note {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.65;
}

/* ============================================================
   CATALOG
============================================================= */
.fc-catalog {
  padding: 20px 0 0;
}

.fc-catalog > .fc-panel-head,
.fc-search {
  margin-inline: 20px;
}

.fc-search {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 2px 10px;
  margin-bottom: 4px;
  border-bottom: 1px solid var(--line);
  color: var(--ink-soft);
  transition: border-color 0.3s;
}

.fc-search:focus-within {
  border-color: var(--ink);
}

.fc-search input {
  flex: 1;
  min-width: 0;
  border: none;
  outline: none;
  background: none;
  color: var(--ink);
  font-size: 14px;
}

.fc-search input::placeholder {
  color: var(--ink-soft);
  opacity: 0.8;
}

.fc-catalog-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 16px 20px 20px;
  scrollbar-width: thin;
}

.fc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px 12px;
}

.fc-tile {
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: 0;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  border-radius: var(--radius-md);
}

.fc-tile-media {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 4 / 5;
  margin-bottom: 10px;
  overflow: hidden;
  border-radius: 999px 999px var(--radius-md) var(--radius-md);
  background: var(--ivory-deep);
  transition: box-shadow 0.3s var(--ease-out);
}

.fc-tile-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s var(--ease-out);
}

.fc-tile-fallback {
  width: 28px;
  height: 28px;
  color: var(--ink-soft);
  opacity: 0.6;
}

.fc-tile:hover:not(.disabled) .fc-tile-media img {
  transform: scale(1.04);
}

.fc-tile.active .fc-tile-media {
  box-shadow: inset 0 0 0 2px var(--ink), 0 0 0 3px var(--paper), 0 0 0 4px var(--rosewood);
}

.fc-tile.disabled {
  cursor: not-allowed;
}

.fc-tile.disabled .fc-tile-media {
  filter: grayscale(0.7);
  opacity: 0.55;
}

.fc-tile.dragging {
  opacity: 0.5;
}

.fc-tile-count {
  position: absolute;
  right: 8px;
  bottom: 8px;
  display: grid;
  place-items: center;
  min-width: 24px;
  height: 24px;
  padding: 0 7px;
  border-radius: 999px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 11.5px;
  font-weight: 500;
}

.fc-tile-name {
  overflow: hidden;
  color: var(--ink);
  font-size: 13.5px;
  font-weight: 500;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-tile-meta {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  margin-top: 2px;
  color: var(--ink-soft);
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}

.fc-tile-stock {
  opacity: 0.75;
}

.fc-tile-flag {
  color: var(--rosewood-dark);
  font-weight: 500;
}

.fc-tile.is-skeleton {
  cursor: default;
}

.fc-tile.is-skeleton .fc-tile-media {
  background: none;
}

.fc-skel-line {
  height: 11px;
  margin-top: 4px;
  border-radius: 6px;
}

.fc-skel-line.short {
  width: 55%;
}

.fc-subhead {
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--line);
}

.fc-fillers {
  display: grid;
  gap: 6px;
}

.fc-filler {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px;
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  background: none;
  text-align: left;
  cursor: pointer;
  transition: background 0.25s, border-color 0.25s;
}

.fc-filler:hover:not(.disabled) {
  background: var(--ivory);
}

.fc-filler.active {
  border-color: var(--ink);
  background: var(--ivory);
}

.fc-filler.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.fc-filler-icon {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--ivory-deep);
  color: #5f7353;
}

.fc-filler-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.fc-filler-copy strong {
  font-size: 13.5px;
  font-weight: 500;
}

.fc-filler-copy small,
.fc-filler-price small {
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 11.5px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-filler-price {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  flex-shrink: 0;
  font-size: 12.5px;
  font-weight: 500;
}

/* ============================================================
   STAGE
============================================================= */
.fc-stage {
  position: relative;
  min-height: 0;
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--stage);
  transition: box-shadow 0.3s var(--ease-out);
}

.fc-stage.over {
  box-shadow: inset 0 0 0 2px var(--rosewood);
}

.fc-viewer {
  position: absolute;
  inset: 0;
  cursor: grab;
}

.fc-stage.placing .fc-viewer {
  cursor: crosshair;
}

.fc-stage.dragging .fc-viewer {
  cursor: grabbing;
}

.fc-toolbar {
  position: absolute;
  top: 16px;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 2px;
  padding: 4px;
  border: 1px solid var(--line-soft);
  border-radius: 999px;
  background: rgba(250, 248, 244, 0.86);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
}

.fc-toolbar-left {
  left: 16px;
}

.fc-toolbar-right {
  right: 16px;
}

.fc-toolbar-rule {
  width: 1px;
  height: 18px;
  margin: 0 4px;
  background: var(--line);
}

.fc-tool {
  position: relative;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: none;
  color: var(--ink);
  cursor: pointer;
  transition: background 0.25s, color 0.25s;
}

.fc-tool:hover:not(:disabled) {
  background: var(--ivory-deep);
}

.fc-tool.on {
  color: var(--rosewood-dark);
}

.fc-tool:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Tooltip */
.fc-tool::after {
  content: attr(data-tip);
  position: absolute;
  top: calc(100% + 10px);
  left: 50%;
  padding: 6px 10px;
  border-radius: 8px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 12px;
  font-weight: 400;
  white-space: nowrap;
  pointer-events: none;
  opacity: 0;
  transform: translate(-50%, -4px);
  transition: opacity 0.2s, transform 0.2s var(--ease-out);
}

.fc-toolbar-right .fc-tool:last-child::after {
  left: auto;
  right: 0;
  transform: translate(0, -4px);
}

.fc-toolbar-left .fc-tool::after {
  left: 0;
  transform: translate(0, -4px);
}

.fc-tool:is(:hover, :focus-visible):not(:disabled)::after {
  opacity: 1;
  transform: translate(-50%, 0);
}

.fc-toolbar-right .fc-tool:last-child:is(:hover, :focus-visible)::after,
.fc-toolbar-left .fc-tool:is(:hover, :focus-visible)::after {
  transform: translate(0, 0);
}

.fc-hint {
  position: absolute;
  left: 50%;
  bottom: 18px;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 12px;
  width: max-content;
  max-width: calc(100% - 32px);
  padding: 10px 18px 10px 14px;
  border: 1px solid var(--line-soft);
  border-radius: 999px;
  background: rgba(250, 248, 244, 0.9);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transform: translateX(-50%);
  transition: background 0.3s, color 0.3s;
}

.fc-hint > .fc-icon {
  color: var(--rosewood);
}

.fc-hint.live {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--ivory);
}

.fc-hint.live > .fc-icon {
  color: var(--gold);
}

.fc-hint-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.4;
}

.fc-hint-copy strong {
  font-size: 13px;
  font-weight: 500;
}

.fc-hint-copy span {
  color: var(--ink-soft);
  font-size: 12px;
}

.fc-hint.live .fc-hint-copy span {
  color: rgba(244, 241, 236, 0.7);
}

.fc-hint-cancel {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  margin-right: -8px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: rgba(244, 241, 236, 0.12);
  cursor: pointer;
}

.fc-hint-cancel .fc-icon {
  width: 14px;
  height: 14px;
}

.fc-stage-center {
  position: absolute;
  inset: 0;
  z-index: 2;
  display: grid;
  place-items: center;
  padding: 24px;
  pointer-events: none;
}

.fc-stage-loading {
  display: grid;
  justify-items: center;
  gap: 16px;
  color: var(--ink-soft);
  font-size: 13.5px;
}

.fc-skel-arch {
  display: block;
  width: 120px;
  height: 160px;
  border-radius: 999px 999px 16px 16px;
}

.fc-stage-message {
  display: grid;
  justify-items: center;
  gap: 8px;
  max-width: 380px;
  padding: 28px;
  border-radius: var(--radius-lg);
  background: var(--paper);
  text-align: center;
  pointer-events: auto;
}

.fc-stage-message > .fc-icon {
  width: 24px;
  height: 24px;
  color: var(--rosewood-dark);
}

.fc-stage-message h3 {
  font-size: 24px;
}

.fc-stage-message p {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.6;
}

/* ============================================================
   INSPECTOR
============================================================= */
.fc-inspector {
  overflow: hidden;
}

.fc-inspector-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  scrollbar-width: thin;
}

.fc-section {
  padding: 20px;
}

.fc-section + .fc-section {
  border-top: 1px solid var(--line-soft);
}

.fc-placed {
  display: grid;
  gap: 4px;
}

.fc-placed-row {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 4px 4px 4px 0;
  border-radius: var(--radius-md);
  transition: background 0.25s;
}

.fc-placed-row:hover {
  background: var(--ivory);
}

.fc-placed-row.active {
  background: var(--ivory-deep);
}

.fc-placed-main {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 12px;
  min-width: 0;
  padding: 4px;
  border: none;
  border-radius: var(--radius-sm);
  background: none;
  text-align: left;
  cursor: pointer;
}

.fc-placed-thumb {
  display: grid;
  place-items: center;
  width: 38px;
  height: 46px;
  flex-shrink: 0;
  overflow: hidden;
  border-radius: 999px 999px 6px 6px;
  background: var(--ivory-deep);
  color: var(--ink-soft);
}

.fc-placed-row.active .fc-placed-thumb {
  background: var(--paper);
}

.fc-placed-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.fc-placed-copy {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.4;
}

.fc-placed-copy strong {
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-placed-copy small {
  color: var(--ink-soft);
  font-size: 12px;
}

.fc-mini {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: none;
  color: var(--ink-soft);
  cursor: pointer;
  transition: background 0.25s, color 0.25s;
}

.fc-mini .fc-icon {
  width: 16px;
  height: 16px;
}

.fc-mini:hover {
  background: var(--paper);
  color: var(--ink);
}

.fc-mini.on {
  color: var(--rosewood-dark);
}

.fc-mini.danger:hover {
  color: var(--rosewood-dark);
}

/* Adjust */
.fc-adjust {
  display: grid;
  gap: 20px;
}

.fc-switch {
  display: flex;
  align-items: center;
  gap: 16px;
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: var(--radius-md);
  background: none;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.25s, background 0.25s;
}

.fc-switch:hover {
  border-color: rgba(30, 27, 24, 0.25);
}

.fc-switch[aria-checked="true"] {
  border-color: rgba(142, 68, 58, 0.3);
  background: rgba(184, 101, 88, 0.06);
}

.fc-switch-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.fc-switch-copy strong {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13.5px;
  font-weight: 500;
}

.fc-switch-copy strong .fc-icon {
  width: 15px;
  height: 15px;
}

.fc-switch-copy small {
  color: var(--ink-soft);
  font-size: 12px;
}

.fc-switch-track {
  position: relative;
  width: 36px;
  height: 20px;
  flex-shrink: 0;
  border-radius: 999px;
  background: rgba(30, 27, 24, 0.16);
  transition: background 0.3s var(--ease-out);
}

.fc-switch-knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 1px 3px rgba(30, 27, 24, 0.2);
  transition: transform 0.3s var(--ease-out);
}

.fc-switch[aria-checked="true"] .fc-switch-track {
  background: var(--rosewood);
}

.fc-switch[aria-checked="true"] .fc-switch-knob {
  transform: translateX(16px);
}

.fc-control-head {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
  font-size: 13px;
}

.fc-control-head label {
  font-weight: 500;
}

.fc-control-head output {
  color: var(--ink-soft);
  font-variant-numeric: tabular-nums;
}

.fc-control-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.fc-step {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  padding: 0;
  border: 1px solid var(--line);
  border-radius: 50%;
  background: none;
  cursor: pointer;
  transition: border-color 0.25s;
}

.fc-step:hover {
  border-color: var(--ink);
}

.fc-step .fc-icon {
  width: 14px;
  height: 14px;
}

/* Range: thin ink track that fills up to the thumb */
.fc-range {
  --fill: 50%;
  flex: 1;
  width: 100%;
  height: 20px;
  margin: 0;
  background: none;
  cursor: pointer;
  appearance: none;
  -webkit-appearance: none;
}

.fc-range::-webkit-slider-runnable-track {
  height: 3px;
  border-radius: 999px;
  background: linear-gradient(to right, var(--ink) var(--fill), rgba(30, 27, 24, 0.14) var(--fill));
}

.fc-range::-moz-range-track {
  height: 3px;
  border-radius: 999px;
  background: rgba(30, 27, 24, 0.14);
}

.fc-range::-moz-range-progress {
  height: 3px;
  border-radius: 999px;
  background: var(--ink);
}

.fc-range::-webkit-slider-thumb {
  width: 16px;
  height: 16px;
  margin-top: -6.5px;
  border: 2px solid var(--paper);
  border-radius: 50%;
  background: var(--rosewood-dark);
  box-shadow: 0 0 0 1px var(--rosewood-dark);
  -webkit-appearance: none;
}

.fc-range::-moz-range-thumb {
  width: 12px;
  height: 12px;
  border: 2px solid var(--paper);
  border-radius: 50%;
  background: var(--rosewood-dark);
  box-shadow: 0 0 0 1px var(--rosewood-dark);
}

.fc-move {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.fc-move-copy {
  display: flex;
  flex-direction: column;
  font-size: 13px;
}

.fc-move-copy span {
  font-weight: 500;
}

.fc-move-copy small {
  color: var(--ink-soft);
  font-size: 12px;
}

.fc-pad {
  display: grid;
  grid-template-columns: repeat(3, 34px);
  grid-template-rows: repeat(3, 34px);
  grid-template-areas:
    ". up ."
    "left . right"
    ". down .";
  padding: 4px;
  border: 1px solid var(--line);
  border-radius: 50%;
}

.fc-pad button {
  display: grid;
  place-items: center;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: none;
  cursor: pointer;
  transition: background 0.25s;
}

.fc-pad button:hover:not(:disabled) {
  background: var(--ivory-deep);
}

.fc-pad button:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.fc-pad .fc-icon {
  width: 16px;
  height: 16px;
}

.fc-pad-up { grid-area: up; }
.fc-pad-left { grid-area: left; }
.fc-pad-right { grid-area: right; }
.fc-pad-down { grid-area: down; }

.fc-text-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  justify-self: start;
  padding: 0 0 3px;
  border: none;
  border-bottom: 1px solid var(--line);
  background: none;
  color: var(--ink-soft);
  font-size: 13px;
  cursor: pointer;
  transition: color 0.25s, border-color 0.25s;
}

.fc-text-btn:hover {
  color: var(--ink);
  border-color: var(--ink);
}

.fc-text-btn .fc-icon {
  width: 15px;
  height: 15px;
}

/* Wrapper and ribbon */
.fc-segment {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 2px;
  padding: 3px;
  margin-bottom: 12px;
  border-radius: 999px;
  background: var(--ivory-deep);
}

.fc-segment-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 8px 12px;
  border: none;
  border-radius: 999px;
  background: none;
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.25s, color 0.25s, box-shadow 0.25s;
}

.fc-segment-btn.active {
  background: var(--paper);
  color: var(--ink);
  box-shadow: 0 1px 3px rgba(30, 27, 24, 0.1);
}

.fc-segment-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 27, 24, 0.15);
}

.fc-options {
  display: grid;
  gap: 6px;
}

.fc-option {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 10px 12px 10px 10px;
  border: 1px solid var(--line-soft);
  border-radius: var(--radius-md);
  background: none;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.25s, background 0.25s;
}

.fc-option:hover {
  border-color: var(--line);
  background: var(--ivory);
}

.fc-option.active {
  border-color: var(--ink);
  background: var(--paper);
}

.fc-option-swatch {
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 27, 24, 0.12);
}

.fc-option-copy {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-width: 0;
}

.fc-option-copy strong {
  font-size: 13.5px;
  font-weight: 500;
}

.fc-option-copy small {
  overflow: hidden;
  color: var(--ink-soft);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-option-price {
  flex-shrink: 0;
  color: var(--ink-soft);
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}

.fc-option-check {
  width: 16px;
  height: 16px;
  color: var(--rosewood-dark);
}

.fc-swatches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.fc-swatch {
  position: relative;
  width: 34px;
  height: 34px;
  padding: 0;
  border: none;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(30, 27, 24, 0.12);
  cursor: pointer;
  transition: box-shadow 0.25s;
}

.fc-swatch.active {
  box-shadow: inset 0 0 0 1px rgba(30, 27, 24, 0.12), 0 0 0 2px var(--paper), 0 0 0 3px var(--ink);
}

.fc-swatch-original {
  display: grid;
  place-items: center;
  color: rgba(30, 27, 24, 0.55);
}

.fc-swatch-original .fc-icon {
  width: 14px;
  height: 14px;
}

.fc-swatch-custom {
  display: grid;
  place-items: center;
  border: 1px dashed rgba(30, 27, 24, 0.3);
  box-shadow: none;
  color: var(--ink-soft);
}

.fc-swatch-custom input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}

.fc-swatch-custom .fc-icon {
  width: 14px;
  height: 14px;
}

/* Order footer */
.fc-order {
  padding: 18px 20px 20px;
  border-top: 1px solid var(--line);
  background: var(--paper);
}

.fc-receipt {
  display: grid;
  gap: 6px;
  margin-bottom: 16px;
  font-size: 13px;
}

.fc-receipt > div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.fc-receipt dt {
  overflow: hidden;
  color: var(--ink-soft);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-receipt dd {
  flex-shrink: 0;
  font-variant-numeric: tabular-nums;
}

.fc-receipt-total {
  align-items: baseline;
  margin-top: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--line-soft);
}

.fc-receipt-total dt {
  color: var(--ink);
  font-weight: 500;
}

.fc-receipt-total dd {
  font-family: var(--font-display);
  font-size: 28px;
  font-weight: 500;
  line-height: 1;
  font-variant-numeric: lining-nums tabular-nums;
}

.fc-order-actions {
  display: grid;
  gap: 8px;
}

.fc-order-btn {
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 14px 24px;
}

.fc-order-btn-outline {
  background: transparent;
  color: var(--ink);
  border-color: var(--line);
}

.fc-order-btn-outline:hover:not(:disabled) {
  border-color: var(--ink);
}

.fc-order-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.bc-btn--dark.fc-order-btn:disabled:hover {
  background: var(--ink);
}

.fc-order-btn .fc-icon {
  width: 16px;
  height: 16px;
}

.fc-order-hint {
  margin-top: 10px;
  color: var(--ink-soft);
  font-size: 12px;
  text-align: center;
}

/* ============================================================
   TOAST
============================================================= */
.fc-toast {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 1100;
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: min(380px, calc(100vw - 32px));
  padding: 12px 18px 12px 14px;
  border-radius: var(--radius-md);
  background: var(--ink);
  color: var(--ivory);
  font-size: 13.5px;
  box-shadow: 0 16px 40px rgba(30, 27, 24, 0.18);
}

.fc-toast .fc-icon {
  color: var(--gold);
}

.fc-toast.error .fc-icon {
  color: #e7a497;
}

.fc-toast-enter-active,
.fc-toast-leave-active {
  transition: opacity 0.3s var(--ease-out), transform 0.3s var(--ease-out);
}

.fc-toast-enter-from,
.fc-toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

/* ============================================================
   RESPONSIVE
============================================================= */
@media (max-width: 1240px) {
  .fc-layout {
    grid-template-columns: 272px minmax(0, 1fr) 316px;
  }

  .fc-layout.left-collapsed {
    grid-template-columns: minmax(0, 1fr) 316px;
  }

  .fc-layout.right-collapsed {
    grid-template-columns: 272px minmax(0, 1fr);
  }
}

@media (max-width: 980px) {
  .fc-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }

  .fc-layout,
  .fc-layout.left-collapsed,
  .fc-layout.right-collapsed,
  .fc-layout.left-collapsed.right-collapsed {
    grid-template-columns: minmax(0, 1fr);
    height: auto;
    min-height: 0;
  }

  .fc-stage {
    order: -1;
    height: 62vh;
    min-height: 420px;
  }

  .fc-catalog-scroll,
  .fc-inspector-scroll {
    overflow: visible;
  }

  .fc-grid {
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  }
}

@media (max-width: 640px) {
  .fc-shell {
    padding: 96px 16px 24px;
  }

  .fc-head-meta {
    width: 100%;
    justify-content: space-between;
  }

  .fc-hint-copy span {
    display: none;
  }

  .fc-tool::after {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .fc-page *,
  .fc-page *::before,
  .fc-page *::after {
    transition-duration: 0.01ms !important;
  }
}
</style>
