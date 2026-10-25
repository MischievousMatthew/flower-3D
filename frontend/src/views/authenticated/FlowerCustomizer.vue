<template>
  <div class="fc-page bc-theme">
    <NavHeader :cartCount="cartCount" :isAuthenticated="isAuthenticated" />

    <div class="fc-shell">
      <div
        ref="layoutContainer"
        class="fc-layout"
        :class="{ fullscreen: isFullscreen }"
      >
        <!-- Start: pick an occasion, then how many flowers -->
        <div v-if="startStep" class="fc-start" role="dialog" aria-modal="true" :aria-label="startStep === 'occasion' ? 'Choose an occasion' : 'Choose how many flowers'">
          <div class="fc-start-card">
            <template v-if="startStep === 'occasion'">
              <p class="fc-start-step">Step 1 of 2</p>
              <h2>What's the bouquet for?</h2>
              <p class="fc-start-lede">Each occasion has its own style and sizes. You can change it later.</p>
              <div class="fc-occasions">
                <button
                  v-for="p in presets"
                  :key="p.key"
                  type="button"
                  class="fc-occasion"
                  :class="{ on: draftOccasion === p.key }"
                  @click="pickOccasion(p.key)"
                >
                  <span class="fc-occasion-icon" v-html="OCCASION_ICONS[OCCASION_INFO[p.key]?.icon] || OCCASION_ICONS.flower"></span>
                  <strong>{{ p.label }}</strong>
                  <span class="fc-occasion-blurb">{{ OCCASION_INFO[p.key]?.blurb }}</span>
                  <span class="fc-occasion-meta">{{ p.sizes.join(" · ") }} flowers · {{ p.wrapper && p.ribbon ? "wrapper & ribbon" : p.wrapper ? "wrapper" : "ribbon, no wrapper" }}</span>
                </button>
              </div>
              <button v-if="preset" type="button" class="fc-text-btn fc-start-cancel" @click="startStep = null">Keep {{ presetLabel }}</button>
            </template>

            <template v-else-if="draftPreset">
              <p class="fc-start-step">{{ presets.length > 1 ? "Step 2 of 2" : "Choose a size" }}</p>
              <h2>How many flowers?</h2>
              <p class="fc-start-lede">{{ draftPreset.label }}: {{ OCCASION_INFO[draftPreset.key]?.blurb }} Bigger bouquets come in a bigger wrap.</p>
              <div class="fc-sizes">
                <button
                  v-for="(size, i) in draftPreset.sizes"
                  :key="size"
                  type="button"
                  class="fc-size"
                  :class="{ on: preset?.key === draftPreset.key && preset?.size === size }"
                  :disabled="!!sizeBlocked(size)"
                  :title="sizeBlocked(size) || `${size} flowers, up to ${fillerLimitFor(size)} fillers`"
                  @click="choosePreset(size)"
                >
                  <strong>{{ size }}</strong>
                  <span>{{ size === 1 ? "flower" : "flowers" }}</span>
                  <small>{{ sizeName(draftPreset.sizes, i) }}</small>
                </button>
              </div>
              <p v-if="draftPreset.sizes.some((s) => sizeBlocked(s))" class="fc-start-note">Sizes smaller than what's on your bouquet are greyed out. Hover one to see why.</p>
              <div class="fc-start-actions">
                <button v-if="presets.length > 1" type="button" class="fc-text-btn" @click="startStep = 'occasion'">← All occasions</button>
                <button v-if="preset" type="button" class="fc-text-btn" @click="startStep = null">Keep {{ presetLabel }}</button>
              </div>
            </template>
          </div>
        </div>

        <!-- The sheet: one step's choices at a time, the receipt underneath -->
        <aside class="fc-sheet" aria-label="Build your bouquet">
          <header class="fc-sheet-head">
            <button type="button" class="fc-back" @click="goBack">
              <BcIcon name="chevronLeft" />
              {{ storeName || "Back" }}
            </button>
            <h1>Build your bouquet</h1>
            <p class="fc-occasion-line">
              <span>{{ occasionLine }}</span>
              <button type="button" class="fc-change" @click="openStart">Change</button>
            </p>
          </header>

          <CustomizerSteps :steps="steps" :active="activeStep" :done="doneSteps" @select="activeStep = $event" />

          <div class="fc-sheet-body">
            <p v-if="!storeId" class="fc-note">Open the customizer from a flower shop's page to see the stems they offer.</p>

            <section v-else-if="activeStep === 'flowers'" class="fc-step-panel">
              <h2>Choose your flowers</h2>
              <p class="fc-step-lede">Pick a flower, then click where it goes on the bouquet. Or drag it on.</p>
              <StemPicker
                kind="flower"
                :items="filteredFlowers"
                :loading="loadingFlowers"
                :search="search"
                :empty-text="search ? `No flowers match “${search}”.` : 'This shop hasn\'t added any stems for custom bouquets yet.'"
                :count-of="(id) => countOfFlower(id)"
                :can-use="canUseFlower"
                :pending-id="pendingFlower && pendingFlower.kind !== 'filler' && pendingFlower.kind !== 'card' ? pendingFlower.id : null"
                :show-fill="maxFlowers - placedFlowers.length > 1"
                :price-label="stemMeta"
                :unavailable-text="() => (placedFlowers.length >= maxFlowers ? 'Full' : 'Sold out')"
                @update:search="search = $event"
                @pick="selectFlower"
                @fill="fillWithFlower"
                @dragstart="onDragStart"
                @dragend="onDragEnd"
              />
            </section>

            <section v-else-if="activeStep === 'fillers'" class="fc-step-panel">
              <h2>Add fillers</h2>
              <p class="fc-step-lede">Greenery and small blooms between the stems. {{ placedFillers.length }} of {{ maxFillers }} used.</p>
              <StemPicker
                kind="filler"
                :items="filteredFillers"
                :search="search"
                :empty-text="search ? `No fillers match “${search}”.` : 'This shop has no fillers.'"
                :count-of="(id) => countOfFlower(id, 'filler')"
                :can-use="canUseFlower"
                :pending-id="pendingFlower?.kind === 'filler' ? pendingFlower.id : null"
                :price-label="(item) => (item.price ? `+${formatPeso(item.price)}` : 'Free') + (item.description ? ` · ${item.description}` : '')"
                :unavailable-text="() => 'Full'"
                @update:search="search = $event"
                @pick="selectFlower"
                @dragstart="onDragStart"
                @dragend="onDragEnd"
              />
            </section>

            <section v-else-if="activeStep === 'wrap'" class="fc-step-panel">
              <h2>Choose the wrap</h2>
              <p class="fc-step-lede">Papers this shop has in stock. Pick one for each layer.</p>

              <div class="fc-segment" role="tablist" aria-label="Wrapper layer">
                <button
                  v-for="layer in wrapLayers"
                  :key="layer.key"
                  type="button"
                  role="tab"
                  class="fc-segment-btn"
                  :class="{ active: activeWrapLayer === layer.key }"
                  :aria-selected="activeWrapLayer === layer.key"
                  @click="activeWrapLayer = layer.key"
                >
                  <span class="fc-segment-dot" :style="{ background: wrap[layer.key].color || originalWrap[layer.key] }"></span>
                  <span class="fc-segment-label">{{ layer.label }}</span>
                </button>
              </div>

              <div v-if="wrapperOptions.length" class="fc-options" role="radiogroup" :aria-label="`${activeWrapLayer === 'outer' ? 'Outer' : 'Inner'} wrapper`">
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
            </section>

            <section v-else-if="activeStep === 'ribbon'" class="fc-step-panel">
              <h2>Choose the ribbon</h2>
              <p class="fc-step-lede">{{ hasWrapper ? "Tied around the wrap." : `${occasion?.label || "This"} bouquet is hand-tied: no wrapper, just the ribbon.` }}</p>
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
            </section>

            <section v-else-if="activeStep === 'card'" class="fc-step-panel">
              <h2>Add a card</h2>
              <p class="fc-step-lede">One message card per bouquet. Pick it, then click the bouquet to place it.</p>
              <StemPicker
                kind="card"
                :items="cards"
                :count-of="(id) => (placedCard?.id === id ? 1 : 0)"
                :can-use="canUseFlower"
                :pending-id="pendingFlower?.kind === 'card' ? pendingFlower.id : null"
                :price-label="(card) => `${cardPriceLabel(card)} · up to ${card.limit} ${card.limit_type}`"
                :unavailable-text="() => 'One per bouquet'"
                @pick="selectFlower"
                @dragstart="onDragStart"
                @dragend="onDragEnd"
              />

              <div v-if="placedCard" class="fc-card-settings">
                <div class="fc-control">
                  <div class="fc-control-head">
                    <span id="fc-card-size-label">Size</span>
                    <output>{{ placedCard.price ? formatPeso(placedCard.price) : "Free" }}</output>
                  </div>
                  <div class="fc-segment" role="radiogroup" aria-labelledby="fc-card-size-label">
                    <button
                      v-for="size in placedCard.sizes"
                      :key="size.label"
                      type="button"
                      role="radio"
                      class="fc-segment-btn"
                      :class="{ active: placedCard.size === size.label }"
                      :aria-checked="placedCard.size === size.label"
                      @click="setPlacedCardSize(size.label)"
                    >
                      {{ size.label }}
                    </button>
                  </div>
                </div>

                <button type="button" class="fc-switch" role="switch" :aria-checked="placedCard.open" @click="setPlacedCardOpen(!placedCard.open)">
                  <span class="fc-switch-copy">
                    <strong><BcIcon name="card" /> {{ placedCard.open ? "Card open" : "Card closed" }}</strong>
                    <small>{{ placedCard.open ? "Your message shows on the inside." : "Open it to show your message." }}</small>
                  </span>
                  <span class="fc-switch-track"><span class="fc-switch-knob"></span></span>
                </button>

                <div class="fc-card-message">
                  <div class="fc-control-head">
                    <span>Message</span>
                    <output>{{ cardUsage(placedCard) }} / {{ placedCard.limit }} {{ placedCard.limit_type }}</output>
                  </div>
                  <p :class="{ empty: !placedCard.message }">{{ placedCard.message || "No message yet." }}</p>
                  <button type="button" class="fc-text-btn" @click="openCardEditor(placedCard.sceneId)">
                    <BcIcon name="card" /> {{ placedCard.message ? "Edit message" : "Write message" }}
                  </button>
                  <small class="fc-card-tip">Tip: double-click the card on the bouquet to write.</small>
                </div>
              </div>
            </section>

            <section v-else-if="activeStep === 'review'" class="fc-step-panel">
              <h2>Review your bouquet</h2>
              <p class="fc-step-lede">
                {{ selectedFlowers.length ? "Click anything to select it on the bouquet." : "Nothing placed yet. Start with the flowers." }}
              </p>
              <ul v-if="selectedFlowers.length" class="fc-placed">
                <li
                  v-for="selection in selectedFlowers"
                  :key="selection.sceneId"
                  class="fc-placed-row"
                  :class="{ active: selectedSceneId === selection.sceneId }"
                >
                  <button type="button" class="fc-placed-main" @click="selectPlacedFlower(selection.sceneId)">
                    <span class="fc-placed-thumb">
                      <img v-if="selection.kind === 'card' && selection.image" :src="selection.image" alt="" />
                      <img v-else-if="isStem(selection) && flowerImage(selection.id)" :src="flowerImage(selection.id)" alt="" />
                      <BcIcon v-else :name="selection.kind === 'filler' ? 'leaf' : selection.kind === 'card' ? 'card' : 'flower'" />
                    </span>
                    <span class="fc-placed-copy">
                      <strong>{{ selection.product_name }}</strong>
                      <small>{{ placedKindLabel(selection) }} · {{ formatPeso(selection.price) }}</small>
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
              <dl class="fc-review-extras">
                <div v-if="hasWrapper && wrapperOptions.length"><dt>Wrap</dt><dd>{{ wrapSummary }}</dd></div>
                <div v-if="showsRibbon">
                  <dt>Ribbon</dt>
                  <dd>
                    <span class="fc-dot" :style="{ background: selectedRibbon?.color_hex || ribbonColor }"></span>
                    {{ selectedRibbon?.name || "None" }}
                  </dd>
                </div>
                <div v-if="cards.length"><dt>Card</dt><dd>{{ placedCard ? `${placedCard.product_name} · ${placedCard.message ? "message written" : "no message yet"}` : "None" }}</dd></div>
              </dl>
            </section>
          </div>

          <OrderReceipt
            :stems="placedFlowers.length"
            :max-stems="maxFlowers"
            :lines="orderLines"
            :total="totalPrice"
            :busy="isCheckingOut"
            :disabled="!placedFlowers.length"
            :editing="!!editingCartItemId"
            :format="formatPeso"
            @add-to-cart="addCart"
            @checkout="requestCheckout"
          />
        </aside>

        <!-- Stage -->
        <main
          ref="stageEl"
          class="fc-stage"
          :class="{ over: isDragOver, dragging: isTransformDragging, placing: pendingFlower || draggedFlower }"
          @dragover.prevent="onCanvasDragOver"
          @dragleave="onCanvasDragLeave"
          @drop.prevent="onCanvasDrop"
        >
          <div ref="viewerContainer" class="fc-viewer"></div>

          <StageTools
            :modes="TRANSFORM_MODES"
            :mode="transformMode"
            :has-target="hasTransformTarget"
            :bouquet-selected="bouquetSelected"
            :ready="sceneReady"
            :fullscreen="isFullscreen"
            :can-reset="selectedFlowers.length > 0"
            @mode="transformMode = $event"
            @toggle-bouquet="toggleBouquetSelection"
            @focus="focusSelection"
            @reset="resetDesign"
            @screenshot="takeScreenshot"
            @fullscreen="toggleFullscreen"
          />

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

          <SelectionCard
            v-if="sceneReady && hasTransformTarget"
            ref="selectionCardEl"
            :title="bouquetSelected ? 'Whole bouquet' : selectedFlower.product_name"
            :subtitle="selectionSubtitle"
            :mode="bouquetSelected ? 'bouquet' : 'flower'"
            :locked="!bouquetSelected && !!selectedFlower?.locked"
            :scale="Number(selectedFlower?.scale ?? 1)"
            :rotation="selectedFlower?.rotation || {}"
            :turn="bouquetSelected ? bouquetTurn : selectedFlower?.rotation?.yDeg || 0"
            :axes="ROTATION_AXES"
            :move-locked="moveLocked"
            :position="selectionCardPosition"
            :side="selectionCardSide"
            :docked="selectionCardDocked"
            @nudge-turn="nudgeTurn"
            @scale="updateSelectedScale"
            @rotate="updateSelectedRotation"
            @nudge-rotate="nudgeSelectedRotation"
            @nudge="nudgeSelectedPosition"
            @lock="toggleSelectedLock"
            @remove="removeFlower(selectedFlower.sceneId)"
            @reset="bouquetSelected ? resetBouquetPlacement() : resetSelectedTransform()"
            @close="clearSelection"
          />

          <div v-if="sceneReady" class="fc-hint" :class="{ live: dragFeedback || pendingFlower }" role="status" aria-live="polite">
            <template v-if="dragFeedback || pendingFlower || draggedFlower">
              <BcIcon name="target" />
              <span class="fc-hint-copy">
                <strong>{{ dragFeedback || stageHint.title }}</strong>
                <span v-if="!dragFeedback">{{ stageHint.detail }}</span>
              </span>
              <button v-if="pendingFlower" type="button" class="fc-hint-cancel" aria-label="Cancel placing" @click="pendingFlower = null">
                <BcIcon name="x" />
              </button>
            </template>
            <span v-else class="fc-hint-keys">
              {{ selectedFlower?.kind === "card" ? stageHint.title : selectedFlower?.locked ? `${selectedFlower.product_name} is locked` : "Drag to look around" }}
              <span class="fc-hint-shortcuts">
                <span class="fc-hint-sep" aria-hidden="true">·</span>
                <kbd>G</kbd> move <kbd>R</kbd> rotate <kbd>S</kbd> size <kbd>F</kbd> focus
              </span>
            </span>
          </div>
        </main>
      </div>
    </div>

    <!-- Asked before checking out a bouquet that still has empty stem slots -->
    <div v-if="incompleteCheckout" class="fc-modal-backdrop" @click.self="incompleteCheckout = false">
      <div
        class="fc-modal fc-confirm"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="fc-confirm-title"
        aria-describedby="fc-confirm-copy"
        @keydown.esc="incompleteCheckout = false"
      >
        <div class="fc-modal-head">
          <div>
            <p class="bc-label">{{ placedFlowers.length }} of {{ maxFlowers }} stems placed</p>
            <h2 id="fc-confirm-title">Your bouquet isn't finished yet</h2>
          </div>
          <button type="button" class="fc-modal-close" aria-label="Close" @click="incompleteCheckout = false">
            <BcIcon name="x" />
          </button>
        </div>
        <p id="fc-confirm-copy" class="fc-confirm-copy">
          There {{ maxFlowers - placedFlowers.length === 1 ? "is" : "are" }} still {{ maxFlowers - placedFlowers.length }}
          empty stem {{ maxFlowers - placedFlowers.length === 1 ? "slot" : "slots" }} on this bouquet. Do you still want to checkout?
        </p>
        <div class="fc-modal-actions">
          <button ref="keepBuildingButton" type="button" class="bc-link fc-modal-cancel" @click="incompleteCheckout = false">Keep building</button>
          <button type="button" class="bc-btn bc-btn--dark fc-confirm-go" @click="confirmIncompleteCheckout">Checkout anyway</button>
        </div>
      </div>
    </div>

    <!-- Card message editor (opened by double-clicking the card) -->
    <div v-if="cardEditor.open" class="fc-modal-backdrop" @click.self="closeCardEditor">
      <form
        class="fc-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="fc-card-editor-title"
        @submit.prevent="saveCardMessage"
        @keydown.esc="closeCardEditor"
      >
        <div class="fc-modal-head">
          <div>
            <p class="bc-label">{{ cardEditor.name }} · {{ cardEditor.size }}</p>
            <h2 id="fc-card-editor-title">Write your card</h2>
          </div>
          <button type="button" class="fc-modal-close" aria-label="Close" @click="closeCardEditor">
            <BcIcon name="x" />
          </button>
        </div>

        <label class="fc-modal-field">
          <span class="bc-sr-only">Card message</span>
          <textarea
            ref="cardMessageInput"
            :value="cardEditor.draft"
            rows="6"
            maxlength="2000"
            :placeholder="`Up to ${cardEditor.limit} ${cardEditor.limitType}`"
            :aria-describedby="'fc-card-counter'"
            @input="onCardDraftInput"
          ></textarea>
        </label>
        <div class="fc-modal-meta">
          <span id="fc-card-counter" class="fc-card-counter" :class="{ full: cardDraftUsed >= cardEditor.limit }" aria-live="polite">
            {{ cardDraftUsed }} / {{ cardEditor.limit }} {{ cardEditor.limitType }}
          </span>
          <span v-if="cardEditor.capped" class="fc-card-capped">
            This card allows up to {{ cardEditor.limit }} {{ cardEditor.limitType }}.
          </span>
        </div>

        <div class="fc-modal-actions">
          <button type="button" class="bc-link fc-modal-cancel" @click="closeCardEditor">Cancel</button>
          <button type="submit" class="bc-btn bc-btn--dark">Save message</button>
        </div>
      </form>
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
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import * as THREE from "three";
import { GLTFLoader } from "three/examples/jsm/loaders/GLTFLoader.js";
import { OrbitControls } from "three/examples/jsm/controls/OrbitControls.js";
import { TransformControls } from "three/examples/jsm/controls/TransformControls.js";
import * as SkeletonUtils from "three/examples/jsm/utils/SkeletonUtils.js";
import api from "../../plugins/axios.js";
import { LEGACY_DESIGN, createBouquetBase, designForOccasion } from "../../utils/bouquetDesigns/index.js";
import {
  DEFAULT_PRESETS,
  LEGACY_PRESET,
  OCCASION_INFO,
  fillerLimitFor,
  presetsOrDefaults,
  sizeName,
  spiralPoints,
  wrapperScaleFor,
} from "../../constants/bouquetPresets.js";
import { OCCASION_ICONS } from "../../constants/occasionIcons.js";
import {
  clampCardMessage,
  countCardMessage,
  createCard,
  disposeCard,
  setCardMessage,
  setCardOpen,
  setCardSize,
} from "../../utils/bouquetCard.js";
import { anchorCard, buildSteps, keyAction, receiptLines } from "../../utils/customizerUi.js";
import NavHeader from "../../layouts/NavHeader.vue";
import BcIcon from "../../components/customizer/CustomizerIcon.js";
import CustomizerSteps from "../../components/customizer/CustomizerSteps.vue";
import StemPicker from "../../components/customizer/StemPicker.vue";
import SelectionCard from "../../components/customizer/SelectionCard.vue";
import OrderReceipt from "../../components/customizer/OrderReceipt.vue";
import StageTools from "../../components/customizer/StageTools.vue";
import { useAuth } from "../../composables/useAuth";
import { useCart } from "../../composables/useCart";
import { checkoutPausedNotice, showCheckoutPaused, usePlatformStatus } from "../../composables/usePlatformStatus";
import { toast as toastify } from "@/utils/snackbar";
import "../../styles/bloomcraft.css";


const TRANSFORM_MODES = [
  { value: "translate", label: "Move", key: "G", icon: "move" },
  { value: "rotate", label: "Rotate", key: "R", icon: "rotate" },
  { value: "scale", label: "Size", key: "S", icon: "scale" },
];
const ROTATION_AXES = [
  { key: "x", label: "Tilt", minus: "Tilt back", plus: "Tilt forward" },
  { key: "y", label: "Turn", minus: "Turn left", plus: "Turn right" },
  { key: "z", label: "Lean", minus: "Lean left", plus: "Lean right" },
];
const MOVE_STEP = 0.08;
const FINE_MOVE_STEP = 0.02;
// Used only when the vendor has not added their own wrappers or ribbons.
// Approximate colours of the classic model's own papers, shown on the layer tabs until a paper is picked.
const ORIGINAL_WRAP = { outer: "#d6b68c", inner: "#ab9196" };
// What the two colour layers are called on each design
const LAYER_NAMES = {
  classic: ["Outer", "Inner"],
  korean: ["Outer sheets", "Inner sheets"],
  basket: ["Basket", "Liner"],
};

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const cartStore = useCart();
const { refresh: refreshPlatform } = usePlatformStatus();

const viewerContainer = ref(null);
const layoutContainer = ref(null);
const stageEl = ref(null);
const selectionCardEl = ref(null);
const flowers = ref([]);
const fillers = ref([]);
const cards = ref([]);
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
// The whole bouquet (wrap + everything on it) can be selected instead of one item
const bouquetSelected = ref(false);
const transformMode = ref("translate");
const bouquetTurn = ref(0);
const isFullscreen = ref(false);
const dragFeedback = ref("");
const ribbonColor = ref("#caa6d9");
const wrapperScale = ref(1);
// Occasion preset: what the bouquet is for and how many flowers it holds.
// The shop's own occasions and sizes arrive with its customization options.
const presets = ref(DEFAULT_PRESETS);
const preset = ref(null); // { key, size } once chosen
const startStep = ref(null); // "occasion" | "size" while the start screen shows
const draftOccasion = ref(null);
const occasion = computed(() => presets.value.find((p) => p.key === preset.value?.key) || null);
const draftPreset = computed(() => presets.value.find((p) => p.key === draftOccasion.value) || null);
const maxFlowers = computed(() => preset.value?.size ?? LEGACY_PRESET.size);
const maxFillers = computed(() => fillerLimitFor(maxFlowers.value));
const hasWrapper = computed(() => occasion.value?.wrapper ?? true);
const hasRibbon = computed(() => occasion.value?.ribbon ?? true);
// A ribbon only shows when the occasion has one and the shop stocks ribbons
const showsRibbon = computed(() => hasRibbon.value && ribbonOptions.value.length > 0);
// The bouquet's design follows its occasion (classic for older bouquets)
const baseDesign = ref("classic"); // what's built in the scene right now
// Editing a bouquet saved before designs existed: keep its original model
// (fillers and all) until the customer picks a different occasion or size
const legacyBase = ref(false);
const baseDefaults = ref({ outer: null, inner: null });
const currentDesign = computed(() => {
  if (legacyBase.value) return LEGACY_DESIGN;
  return occasion.value?.design ?? (preset.value ? designForOccasion(preset.value.key) : "classic");
});
const originalWrap = computed(() => ({
  outer: baseDefaults.value.outer || ORIGINAL_WRAP.outer,
  inner: baseDefaults.value.inner || ORIGINAL_WRAP.inner,
}));
const wrapLayers = computed(() => {
  const [outer, inner] = LAYER_NAMES[baseDesign.value] ?? LAYER_NAMES.classic;
  return [
    { key: "outer", label: outer },
    { key: "inner", label: inner },
  ];
});
const presetLabel = computed(() => (occasion.value ? `${occasion.value.label} · ${maxFlowers.value} stems` : `${maxFlowers.value} stems`));
const isLoading3D = ref(true);
const sceneReady = ref(false);
const bouquetLoadError = ref("");
const isCheckingOut = ref(false);
const toast = reactive({ show: false, message: "", type: "success" });
// Card message editor; the draft is capped at the card's word/letter allowance
const cardEditor = reactive({ open: false, sceneId: null, name: "", size: "", draft: "", limit: 0, limitType: "words", capped: false });
const cardMessageInput = ref(null);

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
// selectedFlowers holds every placed object: stems (kind "flower"), fillers
// (kind "filler") and at most one message card (kind "card").
const isStem = (item) => (item?.kind || "flower") === "flower";
const placedFlowers = computed(() => selectedFlowers.value.filter(isStem));
const placedFillers = computed(() => selectedFlowers.value.filter((item) => item.kind === "filler"));
const placedCard = computed(() => selectedFlowers.value.find((item) => item.kind === "card") || null);
const cardDraftUsed = computed(() => countCardMessage(cardEditor.draft, cardEditor.limitType));
const cardUsage = (card) => countCardMessage(card.message, card.limit_type);
const placedKindLabel = (item) =>
  item.kind === "filler" ? "Filler" : item.kind === "card" ? `Card · ${item.size}` : "Stem";
const cardPriceLabel = (card) => {
  const prices = card.sizes.map((size) => size.price);
  const lowest = Math.min(...prices);
  if (!lowest) return prices.some(Boolean) ? "From free" : "Free";
  return prices.length > 1 ? `From ${formatPeso(lowest)}` : `+${formatPeso(lowest)}`;
};
const findWrapper = (id) => wrapperOptions.value.find((option) => option.id === id) || null;
const selectedOuterWrapper = computed(() => findWrapper(wrap.outer.id));
const selectedInnerWrapper = computed(() => findWrapper(wrap.inner.id));
const activeWrap = computed(() => wrap[activeWrapLayer.value]);
const selectedRibbon = computed(() => ribbonOptions.value.find((option) => option.id === selectedRibbonId.value) || null);
const extrasPrice = computed(
  () =>
    (hasWrapper.value ? Number(selectedOuterWrapper.value?.price || 0) + Number(selectedInnerWrapper.value?.price || 0) : 0) +
    (hasRibbon.value ? Number(selectedRibbon.value?.price || 0) : 0),
);
const totalPrice = computed(() => selectedFlowers.value.reduce((sum, flower) => sum + flower.price, 0) + extrasPrice.value);
const selectedFlower = computed(() => selectedFlowers.value.find((flower) => flower.sceneId === selectedSceneId.value) || null);
const flowersSubtotal = computed(() => placedFlowers.value.reduce((sum, flower) => sum + flower.price, 0));
const fillersSubtotal = computed(() => placedFillers.value.reduce((sum, filler) => sum + filler.price, 0));
const flowerImage = (flowerId) => flowers.value.find((flower) => flower.id === flowerId)?.image || null;
const rangeFill = (value, min, max) => ({ "--fill": `${((Number(value) - min) / (max - min)) * 100}%` });
const hasTransformTarget = computed(() => bouquetSelected.value || !!selectedFlower.value);
const moveLocked = computed(() => !bouquetSelected.value && !!selectedFlower.value?.locked);
const stageHint = computed(() => {
  if (pendingFlower.value) return { title: `Click the wrap to place ${pendingFlower.value.product_name}`, detail: "A marker shows where it will land." };
  if (bouquetSelected.value) {
    return { title: "Whole bouquet selected", detail: "Drag the handles to move it, R to rotate. Esc to deselect." };
  }
  if (selectedFlower.value?.kind === "card") {
    return { title: "Double-click the card to write your message", detail: "Drag it to move it, or open it in the Card panel." };
  }
  if (selectedFlower.value) {
    return selectedFlower.value.locked
      ? { title: `${selectedFlower.value.product_name} is locked`, detail: "Unlock it in Adjust to move it again." }
      : { title: `Drag ${selectedFlower.value.product_name} over the wrap, or use the handles`, detail: "G move · R rotate · S size · arrow keys nudge · Esc deselect" };
  }
  if (placedFlowers.value.length >= maxFlowers.value) return { title: "All stems placed", detail: "Click one to adjust it, or click the wrap to move the whole bouquet." };
  return { title: "Pick a flower, then click the wrap", detail: "Drag empty space to look around, scroll to zoom." };
});


// What the bouquet is called in the occasion line under the title
const DESIGN_NAMES = { classic: "classic wrap", korean: "Korean wrap", hand_tied: "hand-tied", basket: "basket", classic_full: "classic wrap" };
const occasionLine = computed(() => [presetLabel.value, DESIGN_NAMES[baseDesign.value]].filter(Boolean).join(" · "));

// One step's choices show at a time; steps for parts this bouquet doesn't have are left out
const activeStep = ref("flowers");
const steps = computed(() =>
  buildSteps({
    hasFillers: fillers.value.length > 0,
    hasWrapper: hasWrapper.value && wrapperOptions.value.length > 0,
    hasRibbon: showsRibbon.value,
    hasCards: cards.value.length > 0,
  }),
);
const doneSteps = computed(() => {
  const done = [];
  if (placedFlowers.value.length) done.push("flowers");
  if (placedFillers.value.length) done.push("fillers");
  if (wrap.outer.id || wrap.outer.color || wrap.inner.id || wrap.inner.color) done.push("wrap");
  if (selectedRibbonId.value) done.push("ribbon");
  if (placedCard.value) done.push("card");
  return done;
});
watch(steps, (list) => {
  if (!list.some((step) => step.key === activeStep.value)) activeStep.value = "flowers";
});

const orderLines = computed(() =>
  receiptLines({
    flowers: placedFlowers.value,
    fillers: placedFillers.value,
    outerWrapper: hasWrapper.value ? selectedOuterWrapper.value : null,
    innerWrapper: hasWrapper.value ? selectedInnerWrapper.value : null,
    ribbon: hasRibbon.value ? selectedRibbon.value : null,
    card: placedCard.value,
  }),
);
const wrapSummary = computed(() =>
  wrapLayers.value
    .map((layer) => {
      return `${layer.label}: ${findWrapper(wrap[layer.key].id)?.name || "None"}`;
    })
    .join(" · "),
);
const stemMeta = (item) => (item.quantity_in_stock != null ? `${formatPeso(item.price)} · ${item.quantity_in_stock} left` : formatPeso(item.price));

// "2 of 4" for the selected flower among the same kind of stem
const selectionSubtitle = computed(() => {
  const flower = selectedFlower.value;
  if (bouquetSelected.value || !flower) return "";
  const same = selectedFlowers.value.filter((item) => item.id === flower.id && item.kind === flower.kind);
  return same.length > 1 ? `${same.findIndex((item) => item.sceneId === flower.sceneId) + 1} of ${same.length}` : "";
});

// The selection card sits beside whatever is selected on the stage
const selectionCardPosition = ref(null);
const selectionCardSide = ref("right");
const selectionCardDocked = ref(false);

function nudgeTurn(delta) {
  if (bouquetSelected.value) setBouquetTurn(bouquetTurn.value + delta);
  else nudgeSelectedRotation("y", delta);
}

function goBack() {
  if (window.history.length > 1) router.back();
  else router.push("/");
}

let scene, camera, renderer, controls, bouquetRoot, flowerLayer, hoverMarker, animationFrameId = 0, pointerDown = null, transformDrag = null, toastTimer;
// arrangement holds the wrap and every placed item, so moving the bouquet moves them together
let arrangement, gizmo, gizmoStartScale = 1;
// Pointer moves are applied at most once per frame
let queuedPointer = null, pointerFrame = 0;
const loader = new GLTFLoader();
const raycaster = new THREE.Raycaster();
const pointer = new THREE.Vector2();
const bouquetTargets = [];
let dressing = null;
const modelCache = new Map();
const placedObjects = new Map();

const isFiller = (item) => item?.kind === "filler";
const isCard = (item) => item?.kind === "card";
const countOfFlower = (flowerId, kind = "flower") =>
  selectedFlowers.value.filter((flower) => flower.id === flowerId && (flower.kind || "flower") === kind).length;
const canUseFlower = (flower) => {
  if (isCard(flower)) return !placedCard.value; // one card per bouquet
  if (!flower?.model) return false;
  if (isFiller(flower)) return placedFillers.value.length < maxFillers.value;
  return placedFlowers.value.length < maxFlowers.value && flower.quantity_in_stock > countOfFlower(flower.id);
};

let stageResize = null;

onMounted(async () => {
  if (window.ResizeObserver) {
    stageResize = new ResizeObserver(() => queueResize());
    if (stageEl.value) stageResize.observe(stageEl.value);
  }
  if (isAuthenticated.value) await cartStore.initialize?.();
  initScene();
  await loadEditingCustomization();
  // A saved bouquet opens on its own design; a new one starts on the classic cone
  const startDesign = editingCustomization.value ? editingCustomization.value.design || LEGACY_DESIGN : "classic";
  await Promise.all([loadBouquetModel(startDesign), fetchFlowers(), fetchCustomizationOptions()]);
  if (editingCustomization.value) {
    const saved = editingCustomization.value.preset;
    preset.value = saved?.key ? { key: saved.key, size: Number(saved.size) } : { ...LEGACY_PRESET };
    await restoreBouquet(editingCustomization.value);
    if (saved?.key && !occasion.value) {
      showToast("This shop no longer offers that occasion. Choose another one to continue.", "error");
      openStart();
    }
  } else {
    openStart();
  }
  applyStockDressing();
  applyOccasionBuild();
  window.addEventListener("resize", handleResize);
  window.addEventListener("keydown", onKeydown);
  document.addEventListener("fullscreenchange", syncFullscreenState);
});

onUnmounted(() => {
  window.removeEventListener("resize", handleResize);
  stageResize?.disconnect();
  window.removeEventListener("keydown", onKeydown);
  document.removeEventListener("fullscreenchange", syncFullscreenState);
  cleanupScene();
  clearTimeout(toastTimer);
});

function initScene() {
  if (!viewerContainer.value) return;
  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(42, viewerContainer.value.clientWidth / viewerContainer.value.clientHeight, 0.1, 100);
  camera.position.set(0, 2.1, 6.4);
  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.shadowMap.enabled = true;
  viewerContainer.value.appendChild(renderer.domElement);
  controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.target.set(0, 1.5, 0);
  controls.minDistance = 3.8;
  // Dragging the view yourself takes over from the glide back
  controls.addEventListener("start", () => {
    cameraTween = null;
  });
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
  arrangement = new THREE.Group();
  arrangement.add(flowerLayer);
  scene.add(ambient, key, fill, ground, arrangement, hoverMarker);
  setupGizmo();
  setupCanvasInteractions();
  animateScene();
}

async function loadBouquetModel(design = currentDesign.value) {
  isLoading3D.value = true;
  bouquetLoadError.value = "";
  try {
    const base = await createBouquetBase(design, { loadGltf, wrapperScale: wrapperScale.value });
    if (bouquetRoot) {
      arrangement.remove(bouquetRoot);
      disposeBase(bouquetRoot);
    }
    bouquetRoot = base.root;
    bouquetTargets.length = 0;
    bouquetTargets.push(...base.targets);
    arrangement.add(bouquetRoot);
    dressing = base.dressing;
    baseDesign.value = base.design;
    baseDefaults.value = base.defaults;
    dressing.outer.apply(wrap.outer.color);
    dressing.inner.apply(wrap.inner.color);
    applyOccasionBuild();
    sceneReady.value = true;
  } catch (error) {
    console.error("Failed to load bouquet base model:", error);
    bouquetLoadError.value = "The bouquet couldn't be shown. Refresh the page to try again.";
  } finally {
    isLoading3D.value = false;
  }
}

function disposeBase(root) {
  root.traverse((child) => {
    if (!child.isMesh) return;
    child.geometry?.dispose();
    [].concat(child.material).forEach((m) => m?.dispose?.());
  });
}

// A different occasion can mean a different bouquet (wedding → hand-tied, …)
watch(currentDesign, (design) => {
  if (sceneReady.value && design !== baseDesign.value) loadBouquetModel(design);
});

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
    presets.value = presetsOrDefaults(data.presets);
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
    cards.value = (data.cards || [])
      .map((card) => ({
        kind: "card",
        id: Number(card.id),
        product_name: card.name,
        description: card.description || "",
        image: card.image_url || null,
        limit_type: card.card_limit_type === "letters" ? "letters" : "words",
        limit: Number(card.card_limit || 0),
        sizes: (card.card_sizes || []).map((size) => ({ label: size.label, price: Number(size.price || 0) })),
      }))
      .filter((card) => card.sizes.length && card.limit > 0);

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

const cheapest = (options) => [...options].sort((first, second) => first.price - second.price)[0] || null;

// Wrapper and ribbon always come from the shop's stock: anything not picked yet
// (or no longer offered) starts on the cheapest option
function applyStockDressing() {
  if (wrapperOptions.value.length) {
    for (const layer of ["outer", "inner"]) {
      if (!findWrapper(wrap[layer].id)) selectWrapper(layer, cheapest(wrapperOptions.value));
    }
  }
  if (ribbonOptions.value.length && !selectedRibbon.value) selectRibbon(cheapest(ribbonOptions.value));
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
  if (isCard(flower)) {
    draggedFlower.value = { ...flower };
    dragFeedback.value = `Drop the ${flower.product_name} card on the bouquet`;
    event.dataTransfer.effectAllowed = "copy";
    return;
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
  queuePointer(event.clientX, event.clientY);
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

// Blender-style handles: arrows to move, rings to rotate, boxes to size
function setupGizmo() {
  gizmo = new TransformControls(camera, renderer.domElement);
  gizmo.setSize(0.8);
  gizmo.setSpace("world");
  gizmo.addEventListener("dragging-changed", (event) => {
    controls.enabled = !event.value;
    isTransformDragging.value = event.value;
    if (event.value) {
      gizmoStartScale = gizmo.object?.scale.x ?? 1;
      return;
    }
    commitGizmoChange();
  });
  gizmo.addEventListener("objectChange", () => {
    const object = gizmo.object;
    if (!object || object === arrangement || gizmo.mode !== "scale") return;
    // Items only scale evenly: follow whichever handle moved the most
    const { x, y, z } = object.scale;
    const pick = [x, y, z].reduce((best, value) => (Math.abs(value - gizmoStartScale) > Math.abs(best - gizmoStartScale) ? value : best), x);
    object.scale.setScalar(THREE.MathUtils.clamp(pick, 0.25, 2));
  });
  scene.add(gizmo.getHelper());
}

function updateGizmo() {
  if (!gizmo) return;
  const item = selectedFlower.value;
  const target = pendingFlower.value || draggedFlower.value
    ? null
    : bouquetSelected.value
      ? arrangement
      : item && !item.locked
        ? placedObjects.get(item.sceneId)
        : null;
  if (!target) {
    gizmo.detach();
    return;
  }
  if (bouquetSelected.value && transformMode.value === "scale") {
    transformMode.value = "translate"; // the bouquet's size comes from the wrapper; this watcher runs again
    return;
  }
  gizmo.setMode(transformMode.value);
  // The bouquet only spins upright
  const uprightSpin = bouquetSelected.value && transformMode.value === "rotate";
  gizmo.showX = !uprightSpin;
  gizmo.showZ = !uprightSpin;
  gizmo.showY = true;
  if (gizmo.object !== target) gizmo.attach(target);
}
watch([selectedSceneId, bouquetSelected, transformMode, () => selectedFlower.value?.locked, pendingFlower, draggedFlower, sceneReady], updateGizmo, { flush: "post" });

function commitGizmoChange() {
  const object = gizmo?.object;
  if (!object) return;
  if (object === arrangement) {
    bouquetTurn.value = Math.round(THREE.MathUtils.radToDeg(arrangement.rotation.y));
    return;
  }
  commitObjectState(object.userData.sceneId);
}

// Copies an object's position, angle and size into the saved design
function commitObjectState(sceneId) {
  const object = sceneId ? placedObjects.get(sceneId) : null;
  if (!object) return;
  const deg = (radians) => Math.round(THREE.MathUtils.radToDeg(radians) * 10) / 10;
  updateFlowerState(sceneId, {
    position: { x: object.position.x, y: object.position.y, z: object.position.z },
    rotation: { xDeg: deg(object.rotation.x), yDeg: deg(object.rotation.y), zDeg: deg(object.rotation.z) },
    scale: Math.round(object.scale.x * 100) / 100,
  });
}

function queuePointer(clientX, clientY) {
  queuedPointer = { x: clientX, y: clientY };
  if (pointerFrame) return;
  pointerFrame = window.requestAnimationFrame(flushPointer);
}

function flushPointer() {
  pointerFrame = 0;
  const next = queuedPointer;
  queuedPointer = null;
  if (!next) return;
  if (transformDrag?.sceneId) moveSelectedFlower(next.x, next.y, transformDrag.sceneId);
  else if (pendingFlower.value || draggedFlower.value) updateHoverMarker(next.x, next.y);
}

function setupCanvasInteractions() {
  const canvas = renderer.domElement;
  canvas.addEventListener("pointerdown", (event) => {
    // The handles get first say
    if (event.button !== 0 || gizmo?.dragging || gizmo?.axis) return;
    pointerDown = { x: event.clientX, y: event.clientY };
    if (pendingFlower.value || draggedFlower.value) return;

    // Pressing on an item selects it and lets you slide it over the wrap.
    // Pressing the wrap or empty space only turns the view.
    const placedHit = getPlacedFlowerHit(event.clientX, event.clientY);
    if (!placedHit?.sceneId) return;
    event.preventDefault();
    selectPlacedFlower(placedHit.sceneId);
    if (selectedFlowers.value.find((entry) => entry.sceneId === placedHit.sceneId)?.locked) return;
    transformDrag = { sceneId: placedHit.sceneId, pointerId: event.pointerId };
    isTransformDragging.value = true;
    canvas.setPointerCapture?.(event.pointerId);
    controls.enabled = false;
  });
  canvas.addEventListener("pointermove", (event) => {
    if (transformDrag?.sceneId) {
      event.preventDefault();
      queuePointer(event.clientX, event.clientY);
      return;
    }
    if (pendingFlower.value || draggedFlower.value) queuePointer(event.clientX, event.clientY);
  });
  canvas.addEventListener("pointerleave", () => {
    if (!transformDrag) hideHoverMarker();
  });
  canvas.addEventListener("pointerup", async (event) => {
    if (event.button !== 0) return;
    if (transformDrag?.sceneId) {
      endTransformDrag(event.pointerId);
      pointerDown = null;
      return;
    }
    if (!pointerDown) return;
    const moved = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y) > 6;
    pointerDown = null;
    if (moved) return; // that was a view drag

    if (pendingFlower.value) {
      const placement = getPlacement(event.clientX, event.clientY);
      if (!placement) return showToast("Click directly on the bouquet to place the flower.", "error");
      await placeFlowerOnBouquet(pendingFlower.value, placement);
      pendingFlower.value = null;
      hideHoverMarker();
      return;
    }
    if (getPlacedFlowerHit(event.clientX, event.clientY)) return; // selected on press
    if (getPlacement(event.clientX, event.clientY)) selectBouquet();
    else clearSelection();
  });
  canvas.addEventListener("pointercancel", (event) => {
    endTransformDrag(event.pointerId);
    pointerDown = null;
  });
  // Double-click the card to write its message
  canvas.addEventListener("dblclick", (event) => {
    const hit = getPlacedFlowerHit(event.clientX, event.clientY);
    const entry = hit && selectedFlowers.value.find((item) => item.sceneId === hit.sceneId);
    if (entry?.kind === "card") {
      event.preventDefault();
      openCardEditor(entry.sceneId);
    }
  });
}

function getPlacement(clientX, clientY) {
  if (!viewerContainer.value || !camera || !bouquetTargets.length) return null;
  const bounds = viewerContainer.value.getBoundingClientRect();
  pointer.x = ((clientX - bounds.left) / bounds.width) * 2 - 1;
  pointer.y = -((clientY - bounds.top) / bounds.height) * 2 + 1;
  raycaster.setFromCamera(pointer, camera);
  const intersections = raycaster.intersectObjects(shownTargets(), true);
  if (!intersections.length) return null;
  const hit = intersections[0];
  const normal = hit.face?.normal ? hit.face.normal.clone().transformDirection(hit.object.matrixWorld).normalize() : new THREE.Vector3(0, 1, 0);
  const point = hit.point.clone().add(normal.clone().multiplyScalar(0.05));
  flowerLayer.updateMatrixWorld();
  // Items live inside the arrangement, so they keep their place when it moves
  return { point, normal, local: flowerLayer.worldToLocal(point.clone()) };
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
  // Apply the last queued move, then save once
  if (pointerFrame) {
    window.cancelAnimationFrame(pointerFrame);
    flushPointer();
  }
  const sceneId = transformDrag?.sceneId;
  transformDrag = null;
  isTransformDragging.value = false;
  if (controls) controls.enabled = true;
  if (sceneId) commitObjectState(sceneId);
}

async function placeFlowerOnBouquet(flower, placement) {
  if (isCard(flower)) return placeCardOnBouquet(flower, placement);
  const filler = isFiller(flower);
  if (filler && placedFillers.value.length >= maxFillers.value) {
    return showToast(`Maximum of ${maxFillers.value} fillers only.`, "error");
  }
  if (!filler && placedFlowers.value.length >= maxFlowers.value) {
    showToast(`This ${maxFlowers.value}-flower bouquet is full. Choose a bigger size to add more.`, "error");
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
    const at = placement.local ?? placement.point;
    wrapper.position.copy(at);
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
      position: { x: at.x, y: at.y, z: at.z },
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



// ── Message card ──────────────────────────────────────────────────────────
// The card faces the viewer, unlike flower models which face -Z.
const CARD_ROTATION = { xDeg: -8, yDeg: 0, zDeg: 0 };

// `saved` restores a card from a saved bouquet (cart edit)
function placeCardOnBouquet(card, placement, saved = null) {
  if (placedCard.value) return enforceFlowerLimit(card);

  const size = card.sizes.find((entry) => entry.label === saved?.size) || card.sizes[0];
  const message = clampCardMessage(saved?.message || "", card.limit_type, card.limit);
  const open = Boolean(saved?.open);
  const rotation = saved?.rotation
    ? { xDeg: Number(saved.rotation.xDeg || 0), yDeg: Number(saved.rotation.yDeg || 0), zDeg: Number(saved.rotation.zDeg || 0) }
    : { ...CARD_ROTATION };
  const scale = Number(saved?.scale ?? 1);
  const sceneId = saved?.scene_id || `card-${card.id}-${Date.now()}`;

  const object = createCard({ size: size.label, message, open });
  const at = placement.local ?? placement.point;
  object.position.copy(at);
  object.rotation.set(
    THREE.MathUtils.degToRad(rotation.xDeg),
    THREE.MathUtils.degToRad(rotation.yDeg),
    THREE.MathUtils.degToRad(rotation.zDeg),
  );
  object.scale.setScalar(scale);
  object.userData.sceneId = sceneId;
  flowerLayer.add(object);
  placedObjects.set(sceneId, object);

  selectedFlowers.value.push({
    sceneId,
    kind: "card",
    id: card.id,
    product_name: card.product_name,
    image: card.image,
    sizes: card.sizes,
    size: size.label,
    price: size.price,
    limit_type: card.limit_type,
    limit: card.limit,
    message,
    open,
    position: { x: at.x, y: at.y, z: at.z },
    rotation,
    scale,
    locked: Boolean(saved?.locked),
  });
  selectedSceneId.value = sceneId;

  if (!saved) {
    showToast(`${card.product_name} card placed. Write your message.`, "success");
    openCardEditor(sceneId);
  }
}

function setPlacedCardSize(label) {
  const card = placedCard.value;
  const size = card?.sizes.find((entry) => entry.label === label);
  const object = card ? placedObjects.get(card.sceneId) : null;
  if (!card || !size || !object) return;
  setCardSize(object, size.label);
  updateFlowerState(card.sceneId, { size: size.label, price: size.price });
}

function setPlacedCardOpen(open) {
  const card = placedCard.value;
  const object = card ? placedObjects.get(card.sceneId) : null;
  if (!card || !object) return;
  setCardOpen(object, open);
  updateFlowerState(card.sceneId, { open });
}

function openCardEditor(sceneId) {
  const card = selectedFlowers.value.find((entry) => entry.sceneId === sceneId && entry.kind === "card");
  if (!card) return;
  selectedSceneId.value = sceneId;
  Object.assign(cardEditor, {
    open: true,
    sceneId,
    name: card.product_name,
    size: card.size,
    draft: card.message || "",
    limit: card.limit,
    limitType: card.limit_type,
    capped: false,
  });
  nextTick(() => {
    const input = cardMessageInput.value;
    input?.focus();
    input?.setSelectionRange(input.value.length, input.value.length);
  });
}

function closeCardEditor() {
  cardEditor.open = false;
}

// Typing or pasting past the allowance is cut off at the limit
function onCardDraftInput(event) {
  const value = event.target.value;
  const clamped = clampCardMessage(value, cardEditor.limitType, cardEditor.limit);
  cardEditor.capped = clamped !== value;
  if (cardEditor.capped) {
    const caret = Math.min(event.target.selectionStart ?? clamped.length, clamped.length);
    event.target.value = clamped;
    event.target.setSelectionRange(caret, caret);
  }
  cardEditor.draft = clamped;
}

function saveCardMessage() {
  const object = placedObjects.get(cardEditor.sceneId);
  if (!object) return closeCardEditor();
  const message = clampCardMessage(cardEditor.draft, cardEditor.limitType, cardEditor.limit).trim();
  setCardMessage(object, message);
  updateFlowerState(cardEditor.sceneId, { message });
  // Open the card so the customer sees what they wrote
  if (message && !placedCard.value?.open) setPlacedCardOpen(true);
  closeCardEditor();
  showToast(message ? "Card message saved." : "Card message cleared.", "success");
}

function applyRibbonColor(color) {
  ribbonColor.value = color;
  dressing?.applyRibbon(showsRibbon.value ? color : null);
}

function removeFlower(sceneId) {
  selectedFlowers.value = selectedFlowers.value.filter((flower) => flower.sceneId !== sceneId);
  if (selectedSceneId.value === sceneId) {
    selectedSceneId.value = selectedFlowers.value[0]?.sceneId ?? null;
  }
  const object = placedObjects.get(sceneId);
  if (object && gizmo?.object === object) gizmo.detach();
  if (object) {
    flowerLayer.remove(object);
    if (object.userData.isCard) disposeCard(object);
    placedObjects.delete(sceneId);
  }
  if (cardEditor.sceneId === sceneId) closeCardEditor();
}

// ── Occasion presets ───────────────────────────────────────────────────

// Bouquet parts that are showing (a wedding bouquet hides its paper)
function isShown(object) {
  for (let o = object; o; o = o.parent) if (!o.visible) return false;
  return true;
}
const shownTargets = () => bouquetTargets.filter(isShown);

/** Shows or hides the wrapper and ribbon for the chosen occasion */
function applyOccasionBuild() {
  if (!dressing) return;
  dressing.wrapMeshes.forEach((mesh) => (mesh.visible = hasWrapper.value));
  dressing.applyRibbon(showsRibbon.value ? ribbonColor.value : null);
}
watch([occasion, showsRibbon], applyOccasionBuild);

/**
 * Resizes the wrapper for the bouquet size. Everything scales about the floor
 * centre and placed items move outward with it, keeping their own size.
 */
function setWrapperScale(next) {
  const k = next / (wrapperScale.value || 1);
  wrapperScale.value = next;
  if (!bouquetRoot || Math.abs(k - 1) < 1e-6) return;
  bouquetRoot.scale.multiplyScalar(k);
  bouquetRoot.position.multiplyScalar(k);
  for (const [sceneId, object] of placedObjects) {
    object.position.multiplyScalar(k);
    const item = selectedFlowers.value.find((f) => f.sceneId === sceneId);
    if (item) item.position = { x: object.position.x, y: object.position.y, z: object.position.z };
  }
}

/** Why a size can't be picked right now (it's smaller than what's placed), or "" */
function sizeBlocked(size) {
  if (size < placedFlowers.value.length) return `You've placed ${placedFlowers.value.length} flowers. Remove some to choose ${size}.`;
  if (fillerLimitFor(size) < placedFillers.value.length) return `You've placed ${placedFillers.value.length} fillers; this size allows ${fillerLimitFor(size)}.`;
  return "";
}

function openStart() {
  const only = presets.value.length === 1 ? presets.value[0].key : null;
  draftOccasion.value = preset.value?.key ?? only;
  startStep.value = only ? "size" : "occasion";
}

function pickOccasion(key) {
  draftOccasion.value = key;
  startStep.value = "size";
}

function choosePreset(size) {
  const key = draftOccasion.value;
  if (!key || sizeBlocked(size)) return;
  preset.value = { key, size };
  legacyBase.value = false; // a chosen occasion gets its own design
  setWrapperScale(wrapperScaleFor(size));
  startStep.value = null;
}

/** Places copies of one flower evenly over the bouquet top, up to the size */
async function fillWithFlower(flower) {
  if (!bouquetRoot || isFiller(flower) || isCard(flower)) return;
  const room = maxFlowers.value - placedFlowers.value.length;
  const stock = Number(flower.quantity_in_stock || 0) - countOfFlower(flower.id);
  const count = Math.min(room, stock);
  if (count <= 0) return enforceFlowerLimit(flower);

  // Aim at the top of what's showing: the wrapper's opening, or the stems
  const bed = shownTargets().filter((mesh) => mesh.name === "FlowerBed");
  const aimAt = bed.length ? bed : shownTargets();
  const box = new THREE.Box3();
  aimAt.forEach((mesh) => box.expandByObject(mesh));
  const center = box.getCenter(new THREE.Vector3());
  const size = box.getSize(new THREE.Vector3());
  const radius = Math.max(size.x, size.z) * (bed.length ? 0.42 : 0.3);
  const taken = placedFlowers.value.map((f) => placedObjects.get(f.sceneId)?.getWorldPosition(new THREE.Vector3())).filter(Boolean);
  const gap = (radius * 2) / Math.sqrt(maxFlowers.value + 1);

  const down = new THREE.Raycaster();
  const spots = [];
  for (const p of spiralPoints(maxFlowers.value * 2, radius)) {
    if (spots.length >= count) break;
    const origin = new THREE.Vector3(center.x + p.x, box.max.y + 1, center.z + p.z);
    const used = [...taken, ...spots.map((s) => s.point)];
    if (used.some((t) => Math.hypot(t.x - origin.x, t.z - origin.z) < gap * 0.7)) continue;
    down.set(origin, new THREE.Vector3(0, -1, 0));
    const hit = down.intersectObjects(aimAt, true)[0];
    if (!hit) continue;
    const point = hit.point.clone().add(new THREE.Vector3(0, 0.05, 0));
    flowerLayer.updateMatrixWorld();
    spots.push({ point, normal: new THREE.Vector3(0, 1, 0), local: flowerLayer.worldToLocal(point.clone()) });
  }
  if (!spots.length) return showToast("There's no free space left on the bouquet. Move some flowers or choose a bigger size.", "error");

  for (const spot of spots) await placeFlowerOnBouquet(flower, spot);
  const short = count - spots.length;
  showToast(
    short > 0
      ? `Added ${spots.length} ${flower.product_name}. There was only room for ${spots.length}; move flowers to make space for more.`
      : `Added ${spots.length} ${flower.product_name}. Drag any of them to adjust.`,
    "success",
  );
}

function resetDesign() {
  gizmo?.detach();
  selectedFlowers.value.forEach((flower) => {
    const object = placedObjects.get(flower.sceneId);
    if (!object) return;
    flowerLayer.remove(object);
    if (object.userData.isCard) disposeCard(object);
  });
  closeCardEditor();
  selectedFlowers.value = [];
  selectedSceneId.value = null;
  placedObjects.clear();
  pendingFlower.value = null;
  draggedFlower.value = null;
  hideHoverMarker();
}

function selectPlacedFlower(sceneId) {
  bouquetSelected.value = false;
  selectedSceneId.value = sceneId;
}

function selectBouquet() {
  selectedSceneId.value = null;
  bouquetSelected.value = true;
}

function clearSelection() {
  selectedSceneId.value = null;
  bouquetSelected.value = false;
}

// Deselecting in any way (Esc, ×, empty space, Remove, Start over) undoes Focus
watch(hasTransformTarget, (selected) => {
  if (!selected) restoreViewBeforeFocus();
});

function toggleBouquetSelection() {
  if (bouquetSelected.value) clearSelection();
  else selectBouquet();
}

// Slides an item over the wrap while it's dragged; saved when the drag ends
function moveSelectedFlower(clientX, clientY, sceneId = selectedSceneId.value) {
  const placement = getPlacement(clientX, clientY);
  const object = sceneId ? placedObjects.get(sceneId) : null;
  const flower = selectedFlowers.value.find((entry) => entry.sceneId === sceneId);
  if (!placement || !object || flower?.locked) return;
  object.position.copy(placement.local);
}

function updateSelectedRotation(axis, degrees) {
  const sceneId = selectedSceneId.value;
  const object = sceneId ? placedObjects.get(sceneId) : null;
  const flower = selectedFlower.value;
  if (!sceneId || !object || !flower) return;
  if (flower.locked) return;
  // Keep the angle between -180 and 180
  const wrapped = ((((degrees + 180) % 360) + 360) % 360) - 180;
  const nextRotation = { ...flower.rotation, [`${axis}Deg`]: wrapped };
  object.rotation[axis] = THREE.MathUtils.degToRad(wrapped);
  updateFlowerState(sceneId, { rotation: nextRotation });
}

function updateSelectedScale(scale) {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object || flower.locked) return;

  const nextScale = THREE.MathUtils.clamp(scale, 0.25, 2);
  object.scale.setScalar(nextScale);
  updateFlowerState(flower.sceneId, { scale: nextScale });
}

function nudgeSelectedRotation(axis, delta) {
  const flower = selectedFlower.value;
  if (!flower) return;
  updateSelectedRotation(axis, (flower.rotation[`${axis}Deg`] || 0) + delta);
}

function resetSelectedTransform() {
  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object) return;
  const resetRotation = flower.kind === "card" ? { ...CARD_ROTATION } : { xDeg: -5, yDeg: 180, zDeg: 0 };
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

// A world direction for each button, relative to where the camera looks:
// forward is "into the screen" along the floor, up is always straight up.
function directionVector(direction) {
  if (direction === "up") return new THREE.Vector3(0, 1, 0);
  if (direction === "down") return new THREE.Vector3(0, -1, 0);
  const forward = new THREE.Vector3();
  camera.getWorldDirection(forward);
  forward.y = 0;
  if (forward.lengthSq() < 1e-6) forward.set(0, 0, -1);
  forward.normalize();
  const right = new THREE.Vector3().crossVectors(forward, new THREE.Vector3(0, 1, 0)).normalize();
  return {
    forward,
    backward: forward.clone().negate(),
    right,
    left: right.clone().negate(),
  }[direction] ?? new THREE.Vector3();
}

function nudgeSelectedPosition(direction, event = null) {
  const step = event?.shiftKey ? FINE_MOVE_STEP : MOVE_STEP;
  const delta = directionVector(direction).multiplyScalar(step);

  if (bouquetSelected.value) {
    arrangement.position.add(delta);
    return;
  }

  const flower = selectedFlower.value;
  const object = flower ? placedObjects.get(flower.sceneId) : null;
  if (!flower || !object || flower.locked) return;
  // Items sit inside the arrangement, which may be turned
  object.position.add(delta.applyQuaternion(arrangement.quaternion.clone().invert()));
  commitObjectState(flower.sceneId);
}

function setBouquetTurn(degrees) {
  const wrapped = ((((degrees + 180) % 360) + 360) % 360) - 180;
  bouquetTurn.value = wrapped;
  if (arrangement) arrangement.rotation.y = THREE.MathUtils.degToRad(wrapped);
}

function resetBouquetPlacement() {
  if (!arrangement) return;
  arrangement.position.set(0, 0, 0);
  setBouquetTurn(0);
}

// Points the camera at the selection, keeping the current angle and distance
function focusSelection() {
  const target = bouquetSelected.value ? arrangement : selectedFlower.value ? placedObjects.get(selectedFlower.value.sceneId) : null;
  if (!target || !controls) return;
  const box = new THREE.Box3().setFromObject(target);
  if (box.isEmpty()) return;
  const center = box.getCenter(new THREE.Vector3());
  // Remember the view from before the first focus, so deselecting can go back to it
  if (!viewBeforeFocus) viewBeforeFocus = { position: camera.position.clone(), target: controls.target.clone() };
  cameraTween = null;
  const shift = center.sub(controls.target);
  controls.target.add(shift);
  camera.position.add(shift);
  controls.update();
}

let viewBeforeFocus = null;
let cameraTween = null;

// Glides the camera back to where it was before Focus
function restoreViewBeforeFocus() {
  if (!viewBeforeFocus || !camera || !controls) return;
  cameraTween = {
    start: performance.now(),
    fromPosition: camera.position.clone(),
    fromTarget: controls.target.clone(),
    toPosition: viewBeforeFocus.position,
    toTarget: viewBeforeFocus.target,
  };
  viewBeforeFocus = null;
}

function stepCameraTween() {
  if (!cameraTween) return;
  const t = Math.min((performance.now() - cameraTween.start) / 450, 1);
  const eased = 1 - (1 - t) ** 3;
  camera.position.lerpVectors(cameraTween.fromPosition, cameraTween.toPosition, eased);
  controls.target.lerpVectors(cameraTween.fromTarget, cameraTween.toTarget, eased);
  if (t === 1) cameraTween = null;
}

function onKeydown(event) {
  const action = keyAction(
    {
      key: event.key,
      ctrlKey: event.ctrlKey,
      metaKey: event.metaKey,
      altKey: event.altKey,
      inField: !!event.target?.closest?.("input, textarea, select, [contenteditable='true']"),
    },
    {
      ready: sceneReady.value,
      modalOpen: cardEditor.open || incompleteCheckout.value || checkoutPausedNotice.open,
      hasTarget: hasTransformTarget.value,
      bouquetSelected: bouquetSelected.value,
      hasSelectedFlower: !!selectedFlower.value,
      placing: !!pendingFlower.value,
    },
  );
  if (!action) return;
  event.preventDefault();

  if (action === "cancel-placing") pendingFlower.value = null;
  else if (action === "deselect") clearSelection();
  else if (action === "focus") focusSelection();
  else if (action === "remove") removeFlower(selectedFlower.value.sceneId);
  else if (action.startsWith("mode:")) transformMode.value = action.slice(5);
  else if (action.startsWith("nudge:")) nudgeSelectedPosition(action.slice(6), event);
}

function updateFlowerState(sceneId, patch) {
  selectedFlowers.value = selectedFlowers.value.map((flower) =>
    flower.sceneId === sceneId ? { ...flower, ...patch } : flower,
  );
}

function enforceFlowerLimit(flower) {
  if (isCard(flower)) {
    return showToast("A bouquet can have one card. Remove the current card to choose another.", "error");
  }
  if (isFiller(flower)) {
    return showToast(`Maximum of ${maxFillers.value} fillers only.`, "error");
  }
  if (placedFlowers.value.length >= maxFlowers.value) {
    return showToast(`This ${maxFlowers.value}-flower bouquet is full. Choose a bigger size to add more.`, "error");
  }
  if (flower.quantity_in_stock <= countOfFlower(flower.id)) {
    return showToast(`${flower.product_name} is out of stock.`, "error");
  }
  if (!flower.model) {
    return showToast(`${flower.product_name} has no 3D model.`, "error");
  }
}

// Checking out with empty stem slots asks first
const incompleteCheckout = ref(false);
const keepBuildingButton = ref(null);

async function requestCheckout() {
  if (!placedFlowers.value.length || isCheckingOut.value) return;
  // An admin may have paused checkout for every store
  const platform = await refreshPlatform();
  if (platform.checkout_paused) {
    showCheckoutPaused(platform.checkout_pause_message);
    return;
  }
  if (placedFlowers.value.length < maxFlowers.value) {
    incompleteCheckout.value = true;
    nextTick(() => keepBuildingButton.value?.focus());
    return;
  }
  checkout();
}

function confirmIncompleteCheckout() {
  incompleteCheckout.value = false;
  checkout();
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
    ribbon_color: showsRibbon.value ? ribbonColor.value : null,
    wrapper_scale: wrapperScale.value,
    // Older bouquets (original model) keep the old rules: no occasion is sent
    preset: !legacyBase.value && preset.value ? { key: preset.value.key, size: preset.value.size } : null,
    design: baseDesign.value,
    // Prices for these are looked up by id on the server.
    wrapper: hasWrapper.value && selectedOuterWrapper.value ? { id: selectedOuterWrapper.value.id, name: selectedOuterWrapper.value.name } : null,
    inner_wrapper: hasWrapper.value && selectedInnerWrapper.value ? { id: selectedInnerWrapper.value.id, name: selectedInnerWrapper.value.name } : null,
    ribbon: hasRibbon.value && selectedRibbon.value ? { id: selectedRibbon.value.id, name: selectedRibbon.value.name } : null,
    // The card's price comes from its size on the server, and the message is
    // checked against the card's allowance there too.
    card: placedCard.value
      ? {
          scene_id: placedCard.value.sceneId,
          id: placedCard.value.id,
          name: placedCard.value.product_name,
          size: placedCard.value.size,
          message: placedCard.value.message,
          open: placedCard.value.open,
          position: { ...placedCard.value.position },
          rotation: { ...placedCard.value.rotation },
          scale: Number(placedCard.value.scale ?? 1),
          locked: placedCard.value.locked,
        }
      : null,
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
  // Saved before designs existed: it keeps its original model (see legacyBase)
  legacyBase.value = !customization.design;
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

  // The saved card, if the shop still offers it
  const savedCard = customization.card;
  const catalogCard = savedCard && cards.value.find((card) => card.id === Number(savedCard.id));
  if (catalogCard) {
    const position = savedCard.position || {};
    placeCardOnBouquet(
      catalogCard,
      { point: new THREE.Vector3(Number(position.x || 0), Number(position.y || 1.5), Number(position.z || 0.6)) },
      savedCard,
    );
  } else if (savedCard) {
    showToast("The card on this bouquet is no longer offered by the shop.", "error");
  }

  selectedSceneId.value = selectedFlowers.value[0]?.sceneId || null;
}

function takeScreenshot() {
  if (!renderer) return;
  scene.background = new THREE.Color("#f2f5f2");
  renderer.render(scene, camera);
  const link = document.createElement("a");
  link.href = renderer.domElement.toDataURL("image/png");
  scene.background = null;
  link.download = "flower-customizer.png";
  link.click();
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

const anchorBox = new THREE.Box3();
const anchorPoint = new THREE.Vector3();

// The card is placed once per selection, then stays put while the flower is
// sized, turned or moved. Selecting again (or resizing the window) re-places it.
let anchoredTo = null;

function placeSelectionCard() {
  const stage = stageEl.value;
  const card = selectionCardEl.value?.$el;
  const target = gizmo?.object;
  if (!stage || !card || !target || !camera) return;
  const key = bouquetSelected.value ? "bouquet" : selectedSceneId.value;
  if (anchoredTo === key) {
    keepCardInStage(stage, card);
    return;
  }
  anchoredTo = key;
  const width = stage.clientWidth;
  const height = stage.clientHeight;
  const cardWidth = card.offsetWidth || 232;
  const docked = cardWidth > width - 32 || width < 640;
  if (docked !== selectionCardDocked.value) selectionCardDocked.value = docked;
  if (docked) return;

  anchorBox.setFromObject(target);
  // Clear of the object and the gizmo's handles (about 90px across on screen)
  const toScreen = (v) => ({ x: ((v.x + 1) / 2) * width, y: ((1 - v.y) / 2) * height });
  const center = toScreen(anchorBox.getCenter(anchorPoint).project(camera));
  const edge = toScreen(anchorPoint.set(anchorBox.max.x, (anchorBox.min.y + anchorBox.max.y) / 2, (anchorBox.min.z + anchorBox.max.z) / 2).project(camera));
  const gap = Math.min(Math.abs(edge.x - center.x), width / 4) + 96;
  const spot = anchorCard(center, { width, height }, { cardWidth, cardHeight: card.offsetHeight || 200, gap, margin: 72 });
  const previous = selectionCardPosition.value;
  if (!previous || Math.abs(parseFloat(previous.left) - spot.left) > 1 || Math.abs(parseFloat(previous.top) - spot.top) > 1) {
    selectionCardPosition.value = { left: `${Math.round(spot.left)}px`, top: `${Math.round(spot.top)}px` };
  }
  if (spot.side !== selectionCardSide.value) selectionCardSide.value = spot.side;
}

// Opening Fine-tune makes the card taller; nudge it up only if it would spill off the stage
function keepCardInStage(stage, card) {
  if (selectionCardDocked.value || !selectionCardPosition.value) return;
  const top = parseFloat(selectionCardPosition.value.top);
  const maxTop = Math.max(16, stage.clientHeight - 16 - card.offsetHeight);
  if (top > maxTop + 1) selectionCardPosition.value = { ...selectionCardPosition.value, top: `${Math.round(maxTop)}px` };
}

function animateScene() {
  animationFrameId = window.requestAnimationFrame(animateScene);
  if (hasTransformTarget.value) placeSelectionCard();
  else anchoredTo = null;
  stepCameraTween();
  controls?.update();
  renderer?.render(scene, camera);
}

function handleResize() {
  anchoredTo = null;
  if (!viewerContainer.value || !camera || !renderer) return;
  camera.aspect = viewerContainer.value.clientWidth / viewerContainer.value.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(viewerContainer.value.clientWidth, viewerContainer.value.clientHeight);
}

function cleanupScene() {
  window.cancelAnimationFrame(animationFrameId);
  window.cancelAnimationFrame(pointerFrame);
  gizmo?.detach();
  gizmo?.dispose();
  controls?.dispose();
  renderer?.dispose();
  if (renderer?.domElement && viewerContainer.value?.contains(renderer.domElement)) {
    viewerContainer.value.removeChild(renderer.domElement);
  }
}

function loadGltf(url) {
  return new Promise((resolve, reject) => loader.load(url, resolve, undefined, reject));
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

.fc-page :is(button, [role="button"], input, label):focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

.fc-note {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.65;
}

.fc-card-settings {
  display: grid;
  gap: 16px;
}

/* One segment per size the shop offers (1 to 3) */
.fc-card-settings .fc-segment {
  grid-template-columns: none;
  grid-auto-columns: minmax(0, 1fr);
  grid-auto-flow: column;
  margin-bottom: 0;
}

.fc-card-message {
  display: grid;
  gap: 8px;
}

.fc-card-message p {
  max-height: 120px;
  overflow-y: auto;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: var(--radius-md);
  background: #ffffff;
  font-family: var(--font-display);
  font-size: 17px;
  font-style: italic;
  line-height: 1.4;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}

.fc-card-message p.empty {
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 13px;
  font-style: normal;
}

.fc-card-tip {
  color: var(--ink-soft);
  font-size: 11.5px;
}

/* Card message editor */
.fc-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1200;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(38, 45, 57, 0.45);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}

.fc-modal {
  display: grid;
  gap: 14px;
  width: 100%;
  max-width: 480px;
  padding: 26px 26px 22px;
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 30px 80px rgba(38, 45, 57, 0.25);
}

.fc-modal-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.fc-modal-head h2 {
  margin-top: 8px;
  font-size: 32px;
}

.fc-modal-close {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 50%;
  background: var(--ivory-deep);
  color: var(--ink);
  cursor: pointer;
}

.fc-modal-field textarea {
  width: 100%;
  min-height: 150px;
  padding: 16px 18px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #ffffff;
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 20px;
  font-style: italic;
  line-height: 1.45;
  resize: vertical;
}

.fc-modal-field textarea:focus {
  outline: none;
  border-color: var(--ink);
}

.fc-modal-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 6px 12px;
  margin-top: -4px;
}

.fc-card-counter {
  color: var(--ink-soft);
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}

.fc-card-counter.full,
.fc-card-capped {
  color: var(--leaf-deep);
}

.fc-card-capped {
  font-size: 12.5px;
}

.fc-modal-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 20px;
  margin-top: 4px;
}

.fc-modal-cancel {
  border: none;
  background: none;
  cursor: pointer;
}

.fc-modal-close:focus-visible,
.fc-modal-cancel:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
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
  background: rgba(250, 251, 250, 0.9);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  transform: translateX(-50%);
  transition: background 0.3s, color 0.3s;
}

.fc-hint.live {
  background: var(--ink);
  border-color: var(--ink);
  color: var(--ivory);
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
  color: rgba(242, 245, 242, 0.7);
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
  background: rgba(242, 245, 242, 0.12);
  cursor: pointer;
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

.fc-stage-message h3 {
  font-size: 24px;
}

.fc-stage-message p {
  color: var(--ink-soft);
  font-size: 13.5px;
  line-height: 1.6;
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

.fc-mini:hover {
  background: var(--paper);
  color: var(--ink);
}

.fc-mini.on {
  color: var(--leaf-deep);
}

.fc-mini.danger:hover {
  color: var(--leaf-deep);
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
  border-color: rgba(54, 63, 78, 0.25);
}

.fc-switch[aria-checked="true"] {
  border-color: rgba(46, 122, 79, 0.3);
  background: rgba(77, 177, 115, 0.06);
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
  background: rgba(54, 63, 78, 0.16);
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
  box-shadow: 0 1px 3px rgba(54, 63, 78, 0.2);
  transition: transform 0.3s var(--ease-out);
}

.fc-switch[aria-checked="true"] .fc-switch-track {
  background: var(--leaf);
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
  box-shadow: 0 1px 3px rgba(54, 63, 78, 0.1);
}

.fc-segment-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  box-shadow: inset 0 0 0 1px rgba(54, 63, 78, 0.15);
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
  box-shadow: inset 0 0 0 1px rgba(54, 63, 78, 0.12);
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
  color: var(--leaf-deep);
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
  box-shadow: 0 16px 40px rgba(54, 63, 78, 0.18);
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

@media (prefers-reduced-motion: reduce) {
  .fc-page *,
  .fc-page *::before,
  .fc-page *::after {
    transition-duration: 0.01ms !important;
  }
}

.fc-start {
  position: absolute;
  inset: 0;
  z-index: 30;
  display: grid;
  place-items: center;
  padding: 24px;
  border-radius: var(--radius-lg);
  background: color-mix(in srgb, var(--ivory) 88%, transparent);
  backdrop-filter: blur(6px);
  overflow-y: auto;
}
.fc-start-card {
  width: min(880px, 100%);
  padding: 32px;
  border: 1px solid var(--line-soft);
  border-radius: var(--radius-lg);
  background: var(--paper);
  box-shadow: 0 24px 60px rgba(54, 63, 78, 0.12);
}
.fc-start-step {
  margin: 0 0 6px;
  color: var(--leaf);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.fc-start-card h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 32px;
  font-weight: 500;
  color: var(--ink);
}
.fc-start-lede {
  margin: 6px 0 22px;
  color: var(--ink-soft);
  font-size: 14px;
}
.fc-occasions {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 12px;
}
.fc-occasion {
  display: grid;
  justify-items: start;
  gap: 6px;
  padding: 18px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--ivory);
  color: var(--ink);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s var(--ease-out), box-shadow 0.15s;
}
.fc-occasion:hover,
.fc-occasion.on {
  border-color: var(--leaf);
  box-shadow: 0 10px 24px rgba(54, 63, 78, 0.08);
  transform: translateY(-2px);
}
.fc-occasion-icon {
  width: 42px;
  height: 42px;
  margin-bottom: 4px;
}
.fc-occasion-icon :deep(svg) {
  width: 22px;
  height: 22px;
}
.fc-occasion strong {
  font-size: 15px;
}
.fc-occasion-blurb {
  color: var(--ink-soft);
  font-size: 13px;
  line-height: 1.45;
}
.fc-occasion-meta {
  margin-top: 4px;
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 500;
}
.fc-sizes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
}
.fc-size {
  display: grid;
  justify-items: center;
  gap: 2px;
  padding: 22px 14px;
  border: 1px solid var(--line);
  border-radius: 16px;
  background: var(--ivory);
  color: var(--ink);
  font: inherit;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s var(--ease-out);
}
.fc-size strong {
  font-family: var(--font-display);
  font-size: 40px;
  font-weight: 500;
  line-height: 1;
}
.fc-size span {
  color: var(--ink-soft);
  font-size: 13px;
}
.fc-size small {
  margin-top: 6px;
  color: var(--leaf);
  font-size: 12px;
  font-weight: 600;
}
.fc-size:hover:not(:disabled),
.fc-size.on {
  border-color: var(--leaf);
  transform: translateY(-2px);
}
.fc-size:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.fc-start-note {
  margin: 14px 0 0;
  color: var(--ink-soft);
  font-size: 12.5px;
}
.fc-start-actions {
  display: flex;
  justify-content: space-between;
  margin-top: 18px;
}
.fc-start-cancel {
  margin-top: 18px;
}

@media (max-width: 720px) {
  .fc-start {
    padding: 12px;
  }
  .fc-start-card {
    padding: 22px 18px;
  }
  .fc-start-card h2 {
    font-size: 26px;
  }
}

/* ============================================================
   STUDIO LAYOUT: one sheet on the left, the stage edge to edge
   ============================================================ */
.fc-page {
  --radius-lg: 14px;
  --radius-md: 10px;
  --sheet: 340px;
}

.fc-page button,
.fc-page input {
  font-family: var(--font-sans);
}

.fc-shell {
  padding-top: var(--fc-nav, 88px);
}

.fc-layout {
  position: relative;
  display: grid;
  grid-template-columns: var(--sheet) minmax(0, 1fr);
  height: calc(100vh - var(--fc-nav, 88px));
  height: calc(100dvh - var(--fc-nav, 88px));
  min-height: 560px;
  border-top: 1px solid var(--line);
}

.fc-layout:fullscreen,
.fc-layout.fullscreen {
  height: 100vh;
  min-height: 0;
  border-top: 0;
  background: var(--ivory);
}

.fc-layout:-webkit-full-screen {
  height: 100vh;
  background: var(--ivory);
}

/* The sheet */
.fc-sheet {
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr) auto;
  min-height: 0;
  border-right: 1px solid var(--line);
  background: var(--paper);
}

.fc-sheet-head {
  padding: 20px 26px 16px;
  border-bottom: 1px solid var(--line);
}

.fc-back {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-left: -4px;
  padding: 2px 4px;
  border: 0;
  border-radius: 6px;
  background: none;
  color: var(--ink-soft);
  font-size: 12px;
  cursor: pointer;
}

.fc-back:hover {
  color: var(--ink);
}

.fc-back .fc-icon {
  width: 15px;
  height: 15px;
}

.fc-sheet-head h1 {
  margin: 6px 0 4px;
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  line-height: 1.1;
  letter-spacing: -0.01em;
  color: var(--ink);
}

.fc-occasion-line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  font-size: 12.5px;
  color: var(--ink-soft);
}

.fc-change {
  flex-shrink: 0;
  padding: 0 0 1px;
  border: 0;
  border-bottom: 1px solid currentColor;
  background: none;
  color: var(--leaf-deep);
  font-size: 12.5px;
  cursor: pointer;
}

.fc-sheet-body {
  min-width: 0;
  min-height: 0;
  padding: 20px 26px 24px;
  overflow-x: hidden;
  overflow-y: auto;
  scrollbar-width: thin;
}

.fc-step-panel {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  min-width: 0;
  gap: 14px;
  align-content: start;
}

.fc-step-panel > h2 {
  font-family: var(--font-display);
  font-size: 23px;
  font-weight: 500;
  line-height: 1.15;
  color: var(--ink);
}

.fc-step-lede {
  margin-top: -10px !important;
  color: var(--ink-soft);
  font-size: 12.5px;
  line-height: 1.6;
}

.fc-segment {
  border-radius: 10px;
  margin-bottom: 0;
}

.fc-segment-btn {
  min-width: 0;
  padding-inline: 8px !important;
  border-radius: 8px !important;
  font-size: 12.5px !important;
}

.fc-segment-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.fc-options .fc-option {
  min-width: 0;
}

.fc-options .fc-option-copy {
  flex: 1;
  min-width: 0;
}

.fc-options .fc-option-copy strong,
.fc-options .fc-option-copy small {
  overflow-wrap: anywhere;
}

.fc-options .fc-option-price {
  flex-shrink: 0;
  font-size: 12px;
}

.fc-card-settings {
  padding-top: 14px;
  border-top: 1px solid var(--line);
}

.fc-review-extras {
  display: grid;
  gap: 0;
  border-top: 1px solid var(--line);
}

.fc-review-extras > div {
  display: grid;
  grid-template-columns: 70px minmax(0, 1fr);
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--line-soft);
  font-size: 12.5px;
}

.fc-review-extras dt {
  color: var(--ink-soft);
}

.fc-review-extras dd {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ink);
}

.fc-dot {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
  border: 1px solid var(--line);
  border-radius: 50%;
}

.fc-occasion-line {
  align-items: flex-start;
}

/* The stage */
.fc-stage {
  position: relative;
  min-height: 0;
  overflow: hidden;
  background: radial-gradient(120% 90% at 55% 38%, #fafbfa 0%, #eaefeb 55%, #dde5df 100%);
  transition: box-shadow 0.3s var(--ease-out);
}

.fc-stage.over {
  box-shadow: inset 0 0 0 2px var(--leaf);
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

.fc-start {
  border-radius: 0;
}

/* Quiet hint line; turns into a dark pill while placing */
.fc-hint {
  bottom: 18px;
  padding: 6px 12px;
  border: 0;
  border-radius: 10px;
  background: rgba(250, 251, 250, 0.78);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  color: var(--ink-soft);
  font-size: 12px;
}

.fc-hint.live {
  padding: 10px 18px 10px 14px;
  border-radius: 12px;
}

.fc-hint-keys {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 5px;
  text-align: center;
}

.fc-hint-sep {
  margin: 0 4px;
}

.fc-hint-shortcuts {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.fc-hint kbd {
  padding: 1px 6px;
  border: 1px solid var(--line);
  border-bottom-width: 2px;
  border-radius: 5px;
  background: var(--paper);
  color: var(--ink);
  font: 500 11px/1.4 var(--font-sans);
}

/* Phones and small tablets: stage on top, the sheet below */
@media (max-width: 900px) {
  .fc-layout {
    grid-template-columns: minmax(0, 1fr);
    grid-template-rows: 55vh auto;
    height: auto;
    min-height: 0;
  }

  .fc-stage {
    order: -1;
    min-height: 360px;
  }

  .fc-sheet {
    display: block;
    border-right: 0;
  }

  .fc-sheet-head {
    padding: 18px 16px 14px;
  }

  .fc-sheet-body {
    overflow: visible;
    padding: 18px 16px 24px;
  }

  .fc-sheet :deep(.cs-steps) {
    display: flex !important;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .fc-sheet :deep(.cs-step) {
    flex: 1 0 64px;
  }

  .fc-sheet :deep(.or) {
    position: sticky;
    bottom: 0;
    z-index: 8;
    padding: 12px 16px 14px;
    box-shadow: 0 -10px 24px -18px rgba(54, 63, 78, 0.3);
  }

  .fc-hint {
    bottom: 12px;
  }

  .fc-hint-shortcuts {
    display: none !important;
  }
}

.fc-confirm {
  width: min(440px, calc(100vw - 32px));
}

.fc-confirm-copy {
  margin: 4px 0 4px !important;
  color: var(--ink-soft);
  font-size: 14px;
  line-height: 1.6;
}

.fc-page .fc-confirm-go,
.fc-confirm-go {
  color: var(--paper);
}
</style>
