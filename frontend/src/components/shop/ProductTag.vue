<template>
  <span v-if="def" class="ptag" :class="[`ptag--${def.tone}`, { 'ptag--onphoto': onPhoto }]" :title="def.hint">{{ def.label }}</span>
</template>

<script>
/**
 * The tags shown on products. Promotional and recommendation tags come
 * from the server (App\Services\ProductTags, rules set by the admin);
 * stock tags come from the stock; "You may like" and "Recently viewed"
 * come from the row a card sits in.
 */
import { ref } from "vue";
import api from "../../plugins/axios.js";

export const PRODUCT_TAGS = {
  // Promotional
  sale: { label: "Sale", tone: "red", hint: "Has a discounted price" },
  best_seller: { label: "Best seller", tone: "green", hint: "One of the most purchased products" },
  new_arrival: { label: "New arrival", tone: "blue", hint: "Recently added" },
  trending: { label: "Trending", tone: "purple", hint: "Getting a lot of interest right now" },
  // Availability
  in_stock: { label: "In stock", tone: "green", hint: "Available to order" },
  low_stock: { label: "Low stock", tone: "orange", hint: "Only a few left" },
  out_of_stock: { label: "Out of stock", tone: "red", hint: "Currently unavailable" },
  // Shopping and recommendation
  seasonal: { label: "Seasonal", tone: "orange", hint: "Available only during certain seasons" },
  you_may_like: { label: "You may like", tone: "purple", hint: "Picked for you" },
  recommended: { label: "Recommended", tone: "blue", hint: "Recommended by BloomCraft" },
  top_rated: { label: "Top rated", tone: "teal", hint: "Excellent customer ratings" },
  recently_viewed: { label: "Recently viewed", tone: "grey", hint: "You looked at this before" },
};

/** The ranking until the admin's arrives (SiteSetting::PRODUCT_TAG_ORDER) */
export const DEFAULT_TAG_ORDER = [
  "out_of_stock", "sale", "best_seller", "trending", "new_arrival",
  "top_rated", "recommended", "seasonal", "you_may_like", "recently_viewed",
];

// The admin's ranking, asked for once per visit and shared by every card
const tagOrder = ref(DEFAULT_TAG_ORDER);
let tagOrderAsked = false;
export function useTagOrder() {
  if (!tagOrderAsked) {
    tagOrderAsked = true;
    api
      .get("customer/product-tags/order")
      .then(({ data }) => {
        if (Array.isArray(data?.data) && data.data.length) tagOrder.value = data.data;
      })
      .catch(() => {}); // the starting order is fine
  }
  return tagOrder;
}

/** The highest-ranked of these tags, or null */
export function topTag(tags, order = tagOrder.value) {
  const rank = (t) => {
    const i = order.indexOf(t);
    return i === -1 ? order.length : i;
  };
  return [...new Set(tags.filter(Boolean))].sort((a, b) => rank(a) - rank(b))[0] || null;
}

/** Low stock: this many or fewer left */
export const LOW_STOCK = 5;

/** in_stock / low_stock / out_of_stock for a stock count */
export function stockTag(stock) {
  const n = Number(stock || 0);
  return n <= 0 ? "out_of_stock" : n <= LOW_STOCK ? "low_stock" : "in_stock";
}
</script>

<script setup>
import { computed } from "vue";

const props = defineProps({
  tag: { type: String, required: true },
  onPhoto: { type: Boolean, default: false }, // sits on a product photo: solid, with a soft shadow
});
const def = computed(() => PRODUCT_TAGS[props.tag] || null);
</script>

<style scoped>
.ptag {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 8px;
  border: 1px solid var(--ptag-line);
  border-radius: 6px;
  background: var(--ptag-bg);
  color: var(--ptag-ink);
  font-family: var(--font-sans, "Poppins", system-ui, sans-serif);
  font-size: 10.5px;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  white-space: nowrap;
}

.ptag--onphoto {
  box-shadow: 0 2px 6px rgba(30, 39, 57, 0.12);
}

.ptag--red { --ptag-bg: #fdecea; --ptag-ink: #b4332a; --ptag-line: #f3c4be; }
.ptag--green { --ptag-bg: #e7f6ec; --ptag-ink: #1f7a46; --ptag-line: #bfe5cc; }
.ptag--blue { --ptag-bg: #e8f1fd; --ptag-ink: #1f5fae; --ptag-line: #c3d8f6; }
.ptag--purple { --ptag-bg: #f1ebfd; --ptag-ink: #6b3fc4; --ptag-line: #d9c9f7; }
.ptag--orange { --ptag-bg: #fdf0e3; --ptag-ink: #a55a12; --ptag-line: #f2d0a9; }
.ptag--teal { --ptag-bg: #e4f5f3; --ptag-ink: #17766c; --ptag-line: #b9e3de; }
.ptag--grey { --ptag-bg: #eef0f2; --ptag-ink: #4b5563; --ptag-line: #d6dade; }
</style>
