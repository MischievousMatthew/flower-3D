<template>
  <div class="admin-layout">
    <AdminSidebar />

    <main class="main-content">
      <header class="content-header">
        <div class="header-left">
          <button class="mobile-toggle" @click="toggleMobile" aria-label="Toggle Menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12" /><line x1="3" y1="6" x2="21" y2="6" /><line x1="3" y1="18" x2="21" y2="18" /></svg>
          </button>
          <div>
            <h1 class="page-title">Promotion Management</h1>
            <p class="page-subtitle">Banners, featured products, promotions, announcements and the journal. Changes show on the shop as soon as you save.</p>
          </div>
        </div>
        <div class="header-right">
          <a href="/shop" target="_blank" rel="noopener" class="ghost-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" /></svg>
            View shop
          </a>
        </div>
      </header>

      <div class="page-body">
        <nav class="tabs" role="tablist">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" class="tab" :class="{ active: tab === t.key }" :aria-selected="tab === t.key" @click="setTab(t.key)">
            <span class="tab-icon" v-html="t.icon"></span>
            {{ t.label }}
            <span v-if="badge(t.key)" class="tab-badge">{{ badge(t.key) }}</span>
          </button>
        </nav>

        <!-- ═══ Banners ═══ -->
        <section v-if="tab === 'banners'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Homepage banners</h2>
              <p class="panel-sub">Slides at the top of the shop. Without any, the shop shows its standard welcome.</p>
            </div>
            <button type="button" class="primary-btn" @click="editBanner()">+ New banner</button>
          </div>

          <!-- Slideshow settings -->
          <div class="show-settings" aria-labelledby="show-settings-title">
            <div class="show-head">
              <div>
                <h3 id="show-settings-title" class="show-title">Slideshow settings</h3>
                <p class="panel-sub">How the banners play on the shop. Applies to every banner.</p>
              </div>
              <div class="show-save">
                <span v-if="slideshowDirty" class="hint">Unsaved changes</span>
                <button type="button" class="ghost-btn" :disabled="!slideshowDirty || slideshowSaving" @click="resetSlideshow">Undo</button>
                <button type="button" class="primary-btn" :disabled="!slideshowDirty || slideshowSaving" @click="saveSlideshow">{{ slideshowSaving ? "Saving…" : "Save settings" }}</button>
              </div>
            </div>
            <div v-if="!slideshowLoaded" class="sk sk-row"></div>
            <div v-else class="show-grid">
              <div class="show-time" :class="{ muted: !slideshow.autoplay }">
                <label for="slide-seconds" class="field-label">Time on each slide</label>
                <div class="time-row">
                  <input
                    id="slide-seconds"
                    v-model.number="slideshow.seconds"
                    type="range"
                    min="3"
                    max="30"
                    step="1"
                    class="range"
                    :disabled="!slideshow.autoplay"
                    :aria-valuetext="`${slideshow.seconds} seconds`"
                  />
                  <div class="stepper">
                    <button type="button" aria-label="One second less" :disabled="!slideshow.autoplay || slideshow.seconds <= 3" @click="slideshow.seconds--">−</button>
                    <output for="slide-seconds">{{ slideshow.seconds }}s</output>
                    <button type="button" aria-label="One second more" :disabled="!slideshow.autoplay || slideshow.seconds >= 30" @click="slideshow.seconds++">+</button>
                  </div>
                </div>
                <span class="hint">{{ slideshow.autoplay ? `Moves to the next banner every ${slideshow.seconds} seconds (3–30).` : "Autoplay is off, so customers move between banners themselves." }}</span>
              </div>
              <div class="show-toggles">
                <label v-for="opt in SLIDESHOW_TOGGLES" :key="opt.key" class="toggle" :class="{ muted: opt.needsAutoplay && !slideshow.autoplay }">
                  <input v-model="slideshow[opt.key]" type="checkbox" role="switch" :disabled="opt.needsAutoplay && !slideshow.autoplay" />
                  <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
                  <span class="toggle-text">
                    <span class="strong">{{ opt.label }}</span>
                    <span class="hint">{{ opt.hint }}</span>
                  </span>
                </label>
              </div>
            </div>
            <p v-if="!slideshow.autoplay && !slideshow.show_arrows && !slideshow.show_dots" class="form-error">With autoplay, arrows and dots all off, customers will only ever see the first banner.</p>
          </div>

          <SkeletonRows v-if="loading.banners" />
          <p v-else-if="!banners.length" class="empty">No banners yet. Add one to feature a collection, season or store.</p>
          <ul v-else class="media-list">
            <li v-for="(b, i) in banners" :key="b.id" :class="{ off: b.state !== 'showing' }">
              <div class="reorder">
                <button type="button" :disabled="i === 0" aria-label="Move up" @click="move('banners', banners, i, -1)">▲</button>
                <button type="button" :disabled="i === banners.length - 1" aria-label="Move down" @click="move('banners', banners, i, 1)">▼</button>
              </div>
              <img :src="b.image_url" alt="" class="thumb wide" />
              <div class="media-main">
                <span class="strong">{{ b.title }}</span>
                <span class="sub">{{ b.subtitle || "No subtitle" }}</span>
                <span class="sub">Button: {{ b.button_label || "Shop now" }} → {{ describeLink(b) }}</span>
              </div>
              <div class="media-side">
                <span class="pill" :class="`state-${b.state}`">{{ STATE[b.state] }}</span>
                <span class="sub">{{ scheduleText(b) }}</span>
              </div>
              <div class="row-actions">
                <button type="button" class="mini-btn" @click="editBanner(b)">Edit</button>
                <button type="button" class="mini-btn danger" @click="confirmDelete('banners', b, b.title)">Delete</button>
              </div>
            </li>
          </ul>
        </section>

        <!-- ═══ Featured products ═══ -->
        <section v-else-if="tab === 'featured'" class="split">
          <div class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">In bloom this month</h2>
                <p class="panel-sub">The flowers in season right now. Shown near the top of the shop as “In bloom this {{ new Date().toLocaleDateString("en-PH", { month: "long" }) }}”, in this order. Up to 12.</p>
              </div>
              <span class="count-pill">{{ featured.length }} / 12</span>
            </div>
            <SkeletonRows v-if="loading.featured" />
            <p v-else-if="!featured.length" class="empty">Nothing featured yet. Search for a product on the right to add it.</p>
            <ul v-else class="media-list">
              <li v-for="(p, i) in featured" :key="p.featured_id" :class="{ off: !p.live }">
                <div class="reorder">
                  <button type="button" :disabled="i === 0" aria-label="Move up" @click="move('featured', featured, i, -1, 'featured_id')">▲</button>
                  <button type="button" :disabled="i === featured.length - 1" aria-label="Move down" @click="move('featured', featured, i, 1, 'featured_id')">▼</button>
                </div>
                <img v-if="p.image" :src="p.image" alt="" class="thumb" />
                <span v-else class="thumb placeholder">🌸</span>
                <div class="media-main">
                  <span class="strong">{{ p.name }}</span>
                  <span class="sub">{{ p.vendor?.store_name }} · {{ money(p.price) }}</span>
                  <span v-if="!p.live" class="sub warn">Hidden from the shop right now (not available to buy)</span>
                </div>
                <button type="button" class="mini-btn danger" @click="removeFeatured(p)">Remove</button>
              </li>
            </ul>
          </div>
          <div class="panel">
            <h2 class="panel-title">Add a product</h2>
            <p class="panel-sub">Only listings customers can buy right now appear here.</p>
            <div class="search-wrap">
              <svg class="search-ico" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
              <input v-model="productSearch" type="search" class="input search-input" placeholder="Product or store name" />
            </div>
            <SkeletonRows v-if="loading.search" :rows="3" />
            <p v-else-if="!searchResults.length" class="empty small">{{ productSearch ? "No matching products." : "No more products to add." }}</p>
            <ul v-else class="media-list compact">
              <li v-for="p in searchResults" :key="p.id">
                <img v-if="p.image" :src="p.image" alt="" class="thumb" />
                <span v-else class="thumb placeholder">🌸</span>
                <div class="media-main">
                  <span class="strong">{{ p.name }}</span>
                  <span class="sub">{{ p.vendor?.store_name }} · {{ money(p.price) }}</span>
                </div>
                <button type="button" class="mini-btn ok" :disabled="featured.length >= 12" @click="addFeatured(p)">Add</button>
              </li>
            </ul>
          </div>
        </section>

        <!-- ═══ Journal ═══ -->
        <section v-else-if="tab === 'journal'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Journal</h2>
              <p class="panel-sub">Flower care articles and blog posts. Published ones appear in the shop, on the landing page and at /journal.</p>
            </div>
            <button type="button" class="primary-btn" @click="editArticle()">+ New article</button>
          </div>
          <div class="seg">
            <button v-for="f in ARTICLE_FILTERS" :key="f.value" type="button" class="seg-btn" :class="{ active: articleFilter === f.value }" @click="articleFilter = f.value">{{ f.label }}</button>
          </div>
          <SkeletonRows v-if="loading.articles" />
          <p v-else-if="!articles.length" class="empty">No articles{{ articleFilter ? " here" : " yet" }}.</p>
          <ul v-else class="media-list">
            <li v-for="a in articles" :key="a.id" class="clickable" tabindex="0" @click="editArticle(a)" @keydown.enter="editArticle(a)">
              <img v-if="a.cover_url" :src="a.cover_url" alt="" class="thumb" />
              <span v-else class="thumb placeholder">✿</span>
              <div class="media-main">
                <span class="strong">{{ a.title }}<span v-if="a.is_featured" class="star" title="Featured">★</span></span>
                <span class="sub">{{ a.category_label }} · {{ a.reading_minutes }} min read · {{ a.views }} views</span>
              </div>
              <div class="media-side">
                <span class="pill" :class="`article-${a.state}`">{{ ARTICLE_STATE[a.state] }}</span>
                <span class="sub">{{ a.state === "draft" ? `Edited ${timeAgo(a.updated_at)}` : formatDateTime(a.published_at) }}</span>
              </div>
            </li>
          </ul>
        </section>

        <!-- ═══ Announcements & promotions ═══ -->
        <section v-else-if="tab === 'announcements'" class="split even">
          <div v-for="group in ANNOUNCEMENT_GROUPS" :key="group.kind" class="panel">
            <div class="panel-head">
              <div>
                <h2 class="panel-title">{{ group.title }}</h2>
                <p class="panel-sub">{{ group.hint }}</p>
              </div>
              <button type="button" class="primary-btn" @click="editAnnouncement(null, group.kind)">+ New</button>
            </div>

            <!-- Announcement bar settings -->
            <div v-if="group.kind === 'announcement'" class="show-settings narrow" aria-labelledby="bar-settings-title">
              <div class="show-head">
                <div>
                  <h3 id="bar-settings-title" class="show-title">Bar settings</h3>
                  <p class="panel-sub">How the announcement bar plays on the shop.</p>
                </div>
                <div class="show-save">
                  <span v-if="bar.dirty" class="hint">Unsaved changes</span>
                  <button type="button" class="ghost-btn" :disabled="!bar.dirty || bar.saving" @click="bar.reset">Undo</button>
                  <button type="button" class="primary-btn" :disabled="!bar.dirty || bar.saving" @click="bar.save">{{ bar.saving ? "Saving…" : "Save settings" }}</button>
                </div>
              </div>
              <div v-if="!bar.loaded" class="sk sk-row"></div>
              <div v-else class="show-grid">
                <div class="show-time" :class="{ muted: !bar.values.autoplay }">
                  <label for="bar-seconds" class="field-label">Time on each announcement</label>
                  <div class="time-row">
                    <input
                      id="bar-seconds"
                      v-model.number="bar.values.seconds"
                      type="range"
                      min="3"
                      max="30"
                      step="1"
                      class="range"
                      :disabled="!bar.values.autoplay"
                      :aria-valuetext="`${bar.values.seconds} seconds`"
                    />
                    <div class="stepper">
                      <button type="button" aria-label="One second less" :disabled="!bar.values.autoplay || bar.values.seconds <= 3" @click="bar.values.seconds--">−</button>
                      <output for="bar-seconds">{{ bar.values.seconds }}s</output>
                      <button type="button" aria-label="One second more" :disabled="!bar.values.autoplay || bar.values.seconds >= 30" @click="bar.values.seconds++">+</button>
                    </div>
                  </div>
                  <span class="hint">{{ bar.values.autoplay ? `Moves to the next announcement every ${bar.values.seconds} seconds (3–30).` : "Autoplay is off, so customers step through announcements themselves." }}</span>
                </div>
                <div class="show-toggles">
                  <label v-for="opt in BAR_TOGGLES" :key="opt.key" class="toggle" :class="{ muted: opt.needsAutoplay && !bar.values.autoplay }">
                    <input v-model="bar.values[opt.key]" type="checkbox" role="switch" :disabled="opt.needsAutoplay && !bar.values.autoplay" />
                    <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
                    <span class="toggle-text">
                      <span class="strong">{{ opt.label }}</span>
                      <span class="hint">{{ opt.hint }}</span>
                    </span>
                  </label>
                </div>
                <div class="show-time" :class="{ muted: !bar.values.dismissible }">
                  <span id="bar-dismiss-label" class="field-label">A closed announcement stays hidden</span>
                  <div class="seg small" role="radiogroup" aria-labelledby="bar-dismiss-label">
                    <button
                      v-for="opt in DISMISS_FOR"
                      :key="opt.value"
                      type="button"
                      role="radio"
                      class="seg-btn"
                      :class="{ active: bar.values.dismiss_for === opt.value }"
                      :aria-checked="bar.values.dismiss_for === opt.value"
                      :disabled="!bar.values.dismissible"
                      @click="bar.values.dismiss_for = opt.value"
                    >{{ opt.label }}</button>
                  </div>
                  <span class="hint">{{ !bar.values.dismissible ? "Customers can't close announcements, so this doesn't apply." : bar.values.dismiss_for === "forever" ? "Once closed, a customer won't see that announcement again." : `It comes back after ${bar.values.dismiss_for === "1" ? "a day" : "7 days"}.` }}</span>
                </div>
              </div>
              <p v-if="!bar.values.autoplay && !bar.values.show_arrows" class="form-error">With autoplay and arrows both off, customers will only see the first announcement.</p>
            </div>

            <SkeletonRows v-if="loading.announcements" :rows="3" />
            <p v-else-if="!announcementsOf(group.kind).length" class="empty small">None yet.</p>
            <ul v-else class="media-list compact">
              <li v-for="(a, i) in announcementsOf(group.kind)" :key="a.id" :class="{ off: a.state !== 'showing' }">
                <div class="reorder">
                  <button type="button" :disabled="i === 0" aria-label="Move up" @click="moveAnnouncement(group.kind, i, -1)">▲</button>
                  <button type="button" :disabled="i === announcementsOf(group.kind).length - 1" aria-label="Move down" @click="moveAnnouncement(group.kind, i, 1)">▼</button>
                </div>
                <img v-if="a.image_url && a.kind === 'promotion'" :src="a.image_url" alt="" class="thumb" />
                <span v-else-if="a.kind === 'announcement'" class="tone-dot" :style="toneStyle(a.tone)" :title="toneOf(a.tone).label">
                  <svg viewBox="0 0 24 24" aria-hidden="true" v-html="toneOf(a.tone).icon"></svg>
                </span>
                <span v-else class="tone-dot promo-dot" aria-hidden="true">
                  <svg viewBox="0 0 24 24" v-html="PROMO_ICON"></svg>
                </span>
                <div class="media-main">
                  <span class="strong">{{ a.title }}</span>
                  <span class="sub">{{ a.message || "—" }}</span>
                  <span v-if="a.promo_code" class="code">{{ a.promo_code }}</span>
                </div>
                <div class="media-side">
                  <span class="pill" :class="`state-${a.state}`">{{ STATE[a.state] }}</span>
                  <span class="sub">{{ scheduleText(a) }}</span>
                </div>
                <div class="row-actions">
                  <button type="button" class="mini-btn" @click="editAnnouncement(a)">Edit</button>
                  <button type="button" class="mini-btn danger" @click="confirmDelete('announcements', a, a.title)">Delete</button>
                </div>
              </li>
            </ul>
          </div>
        </section>

        <!-- ── Product tags: the rules behind Best seller, Trending, New arrival… ── -->
        <section v-else-if="tab === 'tags'" class="panel">
          <div class="panel-head">
            <div>
              <h2 class="panel-title">Product tags</h2>
              <p class="panel-sub">The tags on shop cards and product pages. They update on their own from sales, carts, hearts and reviews. Set which ones show and how many products get each.</p>
            </div>
            <div class="show-save">
              <span v-if="tagsDirty" class="hint">Unsaved changes</span>
              <button type="button" class="ghost-btn" :disabled="!tagsDirty || tagsSaving" @click="resetTagRules">Undo</button>
              <button type="button" class="primary-btn" :disabled="!tagsDirty || tagsSaving" @click="saveTagRules">{{ tagsSaving ? "Saving…" : "Save rules" }}</button>
            </div>
          </div>

          <SkeletonRows v-if="!tagsLoaded" :rows="4" />
          <template v-else>
            <!-- The ranking: a shop card shows only the highest tag a product has -->
            <div class="tag-order">
              <div class="tag-order-head">
                <span class="field-label">Tag order</span>
                <span class="hint">A shop card shows one tag. Drag to change the order, or use the arrows.</span>
              </div>
              <label class="toggle tag-rotate">
                <input v-model="tagRules.rotate_hourly" type="checkbox" role="switch" />
                <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
                <span class="toggle-text">
                  <span class="strong">Change the card's tag every hour</span>
                  <span class="hint">{{ tagRules.rotate_hourly
                    ? "A product with several tags shows a different one each hour, so cards don't get stuck on one. Out of stock and a row's own tag never change."
                    : "Cards always show the highest tag on this list that the product has." }}</span>
                </span>
              </label>
              <ol class="tag-rank" aria-label="Tag order, highest first">
                <li
                  v-for="(t, i) in tagRules.order"
                  :key="t"
                  :ref="(el) => (rankEls[t] = el)"
                  class="rank-item"
                  :class="{ dragging: dragTag === t, off: !tagIsOn(t) }"
                >
                  <button
                    type="button"
                    class="rank-handle"
                    :aria-label="'Move ' + tagName(t) + ', number ' + (i + 1) + '. Use the up and down arrow keys.'"
                    @pointerdown="startTagDrag($event, t)"
                    @keydown.up.prevent="moveTag(i, -1)"
                    @keydown.down.prevent="moveTag(i, 1)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.4" /><circle cx="15" cy="6" r="1.4" /><circle cx="9" cy="12" r="1.4" /><circle cx="15" cy="12" r="1.4" /><circle cx="9" cy="18" r="1.4" /><circle cx="15" cy="18" r="1.4" /></svg>
                  </button>
                  <span class="rank-num">{{ i + 1 }}</span>
                  <ProductTag :tag="t" />
                  <span class="rank-note">{{ tagNote(t) }}</span>
                  <span class="rank-arrows">
                    <button type="button" :disabled="i === 0" :aria-label="'Move ' + tagName(t) + ' up'" @click="moveTag(i, -1)">▲</button>
                    <button type="button" :disabled="i === tagRules.order.length - 1" :aria-label="'Move ' + tagName(t) + ' down'" @click="moveTag(i, 1)">▼</button>
                  </span>
                </li>
              </ol>
            </div>

            <div class="tag-grid">
              <article v-for="rule in TAG_RULES" :key="rule.tag" class="tag-card" :class="{ off: !tagRules[rule.enable] }">
                <header class="tag-card-head">
                  <ProductTag :tag="rule.tag" />
                  <span class="tag-count" :class="{ zero: !tagCounts[rule.tag] }" aria-live="polite">{{ countText(rule) }}</span>
                  <label class="toggle tag-switch">
                    <input v-model="tagRules[rule.enable]" type="checkbox" role="switch" :aria-label="'Show the ' + rule.name + ' tag'" />
                    <span class="toggle-track" aria-hidden="true"><span class="toggle-thumb"></span></span>
                  </label>
                </header>
                <p class="tag-desc">{{ rule.desc }}</p>
                <div v-if="rule.fields.length" class="tag-fields">
                  <label v-for="field in rule.fields" :key="field.key" class="tag-field">
                    <span>{{ field.label }}</span>
                    <input
                      v-model.number="tagRules[field.key]"
                      type="number"
                      class="input tag-num"
                      :min="field.min"
                      :max="field.max"
                      :step="field.step || 1"
                      :disabled="!tagRules[rule.enable]"
                    />
                    <span>{{ field.suffix }}</span>
                  </label>
                </div>
                <p v-if="rule.note" class="hint">{{ rule.note }}</p>
              </article>
            </div>

            <div class="tag-fixed">
              <span class="field-label">Always on</span>
              <p class="hint">
                <ProductTag tag="in_stock" /> <ProductTag tag="low_stock" /> <ProductTag tag="out_of_stock" />
                follow the stock (low stock is 5 or fewer).
                <ProductTag tag="you_may_like" /> shows on that row of the product page.
              </p>
            </div>
          </template>
        </section>

      </div>
    </main>

    <!-- ═══ Editor drawer ═══ -->
    <Transition name="fade"><div v-if="editor.type" class="drawer-backdrop" @click="closeEditor"></div></Transition>
    <Transition name="slide">
      <aside v-if="editor.type" class="drawer" role="dialog" aria-modal="true">
        <header class="drawer-head">
          <h2>{{ editorTitle }}</h2>
          <button type="button" class="icon-btn" aria-label="Close" @click="closeEditor"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></svg></button>
        </header>

        <form id="editor-form" class="drawer-body" @submit.prevent="saveEditor">
          <!-- Image -->
          <div v-if="!(editor.type === 'announcement' && (form.kind === 'announcement' || editor.reviewing))" class="image-field" :class="{ required: editor.type === 'banner' }">
            <span class="field-label">{{ editor.type === "article" ? "Cover photo" : editor.type === "banner" ? "Banner image *" : "Image (optional)" }}</span>
            <label class="image-drop" :class="{ filled: imagePreview }">
              <img v-if="imagePreview" :src="imagePreview" alt="" />
              <span v-else>Click to choose a JPG, PNG or WebP (max 5 MB)<template v-if="editor.type === 'banner'"><br />Wide photos (about 2400 × 1100) look best</template></span>
              <input type="file" accept="image/jpeg,image/png,image/webp" @change="pickImage" />
            </label>
            <button v-if="editor.type === 'article' && imagePreview" type="button" class="link-btn" @click="clearImage">Remove photo</button>
          </div>

          <!-- Banner -->
          <template v-if="editor.type === 'banner'">
            <label class="field"><span>Small heading</span><input v-model="form.eyebrow" class="input" placeholder="e.g. Spring collection" maxlength="60" /></label>
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="140" required /></label>
            <label class="field"><span>Subtitle</span><textarea v-model="form.subtitle" class="input" rows="2" maxlength="300"></textarea></label>
            <label class="field"><span>Button text</span><input v-model="form.button_label" class="input" placeholder="Shop now" maxlength="40" /></label>
            <LinkPicker v-model:type="form.link_type" v-model:value="form.link_value" :options="linkOptions" />
            <div class="fs-cta">
              <button type="button" class="ghost-btn" :disabled="!imagePreview" @click="fullPreview = true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3" /></svg>
                See it full screen
              </button>
              <small class="hint">{{ imagePreview ? "Shows the banner at the size customers see it." : "Choose an image first." }}</small>
            </div>
          </template>

          <!-- Announcement / promotion: review before posting -->
          <template v-else-if="editor.type === 'announcement' && editor.reviewing">
            <div class="review-intro">
              <h3>Check how it looks</h3>
              <p>This is how customers will see it on the shop. Go back to change anything.</p>
            </div>
            <figure class="review-stage bc-theme">
              <figcaption class="review-where">
                <span>{{ form.kind === "promotion" ? "Shop page, beside “Shop by occasion”" : "Top of the shop" }}</span>
                <button type="button" class="fs-open" @click="fullPreview = true">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3" /></svg>
                  Full screen
                </button>
              </figcaption>
              <AnnouncementBar v-if="form.kind === 'announcement'" preview :items="[previewItem]" :settings="bar.values" />
              <PromoCard v-else preview :promotions="[previewItem]" />
            </figure>
            <dl class="review-facts">
              <div>
                <dt>When</dt>
                <dd>{{ reviewWhen }}</dd>
              </div>
              <div>
                <dt>Button</dt>
                <dd>{{ form.link_type ? `“${previewButton}” goes to ${describeLink(form)}` : "No button" }}</dd>
              </div>
              <div v-if="form.kind === 'promotion'">
                <dt>Promo code</dt>
                <dd>{{ form.promo_code.trim() ? `${form.promo_code.trim()} (customers tap it to copy)` : "None" }}</dd>
              </div>
              <div v-else>
                <dt>Colour</dt>
                <dd>{{ toneOf(form.tone).label }}</dd>
              </div>
            </dl>
            <p v-if="form.link_label.trim() && !form.link_type" class="review-note">You typed button text but didn't choose where it goes, so no button shows.</p>
            <p v-if="!form.is_active" class="review-note">It's switched off, so customers won't see it until you switch it on.</p>
          </template>

          <!-- Announcement / promotion -->
          <template v-else-if="editor.type === 'announcement'">
            <div class="seg wide">
              <button type="button" class="seg-btn" :class="{ active: form.kind === 'announcement' }" @click="form.kind = 'announcement'">Announcement</button>
              <button type="button" class="seg-btn" :class="{ active: form.kind === 'promotion' }" @click="form.kind = 'promotion'">Promotion</button>
            </div>
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="120" required :placeholder="form.kind === 'promotion' ? 'e.g. Mother\'s Day pre-orders' : 'e.g. Free delivery this weekend'" /></label>
            <label class="field"><span>Message</span><textarea v-model="form.message" class="input" rows="2" maxlength="300"></textarea></label>
            <label v-if="form.kind === 'promotion'" class="field">
              <span>Promo code</span>
              <input v-model="form.promo_code" class="input mono" maxlength="40" placeholder="e.g. MOM10" />
              <small class="hint">Shown for customers to copy. Letters, numbers, - and _ only. Vendors honour it at checkout or in chat.</small>
            </label>
            <div v-else class="field">
              <span id="tone-label">Colour and icon</span>
              <div class="tones" role="radiogroup" aria-labelledby="tone-label">
                <button
                  v-for="t in TONES"
                  :key="t.value"
                  type="button"
                  role="radio"
                  class="tone-btn"
                  :class="{ active: form.tone === t.value }"
                  :style="{ '--swatch': t.swatch, '--swatch-ink': t.ink }"
                  :aria-checked="form.tone === t.value"
                  :aria-label="t.label"
                  :title="t.label"
                  @click="form.tone = t.value"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true" v-html="t.icon"></svg>
                </button>
              </div>
              <small class="hint">The bar shows this icon in this colour. You'll see the full bar before posting.</small>
            </div>
            <label class="field"><span>Button text</span><input v-model="form.link_label" class="input" placeholder="Learn more" maxlength="40" /></label>
            <LinkPicker v-model:type="form.link_type" v-model:value="form.link_value" :options="linkOptions" optional />
          </template>

          <!-- Article -->
          <template v-else-if="editor.type === 'article'">
            <label class="field"><span>Title *</span><input v-model="form.title" class="input" maxlength="160" required /></label>
            <div class="two">
              <label class="field">
                <span>Topic *</span>
                <select v-model="form.category" class="input">
                  <option v-for="(label, key) in articleCategories" :key="key" :value="key">{{ label }}</option>
                </select>
              </label>
              <label class="check"><input v-model="form.is_featured" type="checkbox" /> Feature at the top of the journal</label>
            </div>
            <label class="field"><span>Short summary</span><textarea v-model="form.excerpt" class="input" rows="2" maxlength="300" placeholder="Shown on article cards. Left empty, the start of the article is used."></textarea></label>
            <div class="field">
              <div class="body-head">
                <span>Article *</span>
                <div class="seg small">
                  <button type="button" class="seg-btn" :class="{ active: !previewBody }" @click="previewBody = false">Write</button>
                  <button type="button" class="seg-btn" :class="{ active: previewBody }" @click="previewBody = true">Preview</button>
                </div>
              </div>
              <textarea v-if="!previewBody" v-model="form.body" class="input body-input" rows="16" required></textarea>
              <div v-else class="body-preview">
                <template v-for="(block, i) in articleBlocks(form.body)" :key="i">
                  <h2 v-if="block.type === 'h2'">{{ block.text }}</h2>
                  <h3 v-else-if="block.type === 'h3'">{{ block.text }}</h3>
                  <blockquote v-else-if="block.type === 'quote'">{{ block.text }}</blockquote>
                  <ul v-else-if="block.type === 'ul'"><li v-for="(it, j) in block.items" :key="j">{{ it }}</li></ul>
                  <ol v-else-if="block.type === 'ol'"><li v-for="(it, j) in block.items" :key="j">{{ it }}</li></ol>
                  <p v-else>{{ block.text }}</p>
                </template>
                <p v-if="!form.body.trim()" class="sub">Nothing written yet.</p>
              </div>
              <small class="hint">Formatting: <code>## Heading</code> · <code>### Smaller heading</code> · <code>- bullet</code> · <code>1. step</code> · <code>&gt; quote</code> · blank line for a new paragraph</small>
            </div>
            <div v-if="editor.item" class="publish-box">
              <div>
                <span class="strong">Status: {{ ARTICLE_STATE[editor.item.state] }}</span>
                <span class="sub" v-if="editor.item.state !== 'draft'">{{ editor.item.state === "scheduled" ? "Goes live" : "Published" }} {{ formatDateTime(editor.item.published_at) }}</span>
                <a v-if="editor.item.state === 'published'" :href="`/journal/${editor.item.slug}`" target="_blank" rel="noopener" class="link-btn">View on site ↗</a>
              </div>
              <label v-if="editor.item.state === 'draft'" class="field small-field">
                <span>Publish later (optional)</span>
                <input v-model="form.publish_at" type="datetime-local" class="input" />
              </label>
            </div>
          </template>

          <!-- Schedule (banners + announcements) -->
          <div v-if="editor.type !== 'article' && !editor.reviewing" class="schedule">
            <span class="field-label">Schedule (optional)</span>
            <div class="two">
              <label class="field"><span>Starts</span><input v-model="form.starts_at" type="datetime-local" class="input" /></label>
              <label class="field"><span>Ends</span><input v-model="form.ends_at" type="datetime-local" class="input" /></label>
            </div>
            <label class="check"><input v-model="form.is_active" type="checkbox" /> Switched on</label>
          </div>

          <p v-if="editor.error" class="form-error">{{ editor.error }}</p>
        </form>

        <footer class="drawer-foot">
          <button v-if="editor.type === 'article' && editor.item" type="button" class="ghost-btn danger-text" @click="confirmDelete('articles', editor.item, editor.item.title)">Delete</button>
          <span class="spacer"></span>
          <template v-if="editor.type === 'article'">
            <button v-if="editor.item && editor.item.state !== 'draft'" type="button" class="ghost-btn" :disabled="saving" @click="unpublish">Move to drafts</button>
            <button type="submit" form="editor-form" class="ghost-btn" :disabled="saving">{{ saving ? "Saving…" : editor.item?.state === "draft" || !editor.item ? "Save draft" : "Save changes" }}</button>
            <button v-if="!editor.item || editor.item.state === 'draft'" type="button" class="primary-btn" :disabled="saving" @click="saveAndPublish">
              {{ form.publish_at ? "Schedule" : "Publish" }}
            </button>
          </template>
          <template v-else-if="editor.type === 'announcement'">
            <button v-if="editor.reviewing" type="button" class="ghost-btn" :disabled="saving" @click="editor.reviewing = false">Back to edit</button>
            <button type="submit" form="editor-form" class="primary-btn" :disabled="saving">
              {{ saving ? "Posting…" : !editor.reviewing ? "Review before posting" : editor.item ? "Save changes" : "Post it" }}
            </button>
          </template>
          <button v-else type="submit" form="editor-form" class="primary-btn" :disabled="saving">{{ saving ? "Saving…" : "Save" }}</button>
        </footer>
      </aside>
    </Transition>

    <!-- Full-screen preview: the shop's own components at real size -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="fullPreview" ref="fsEl" class="fs-preview bc-theme" role="dialog" aria-modal="true" aria-label="Full-screen preview" tabindex="-1">
          <header class="fs-bar">
            <span class="fs-label">
              <strong>Preview</strong>
              <span>How customers will see this {{ editor.type === "banner" ? "banner" : form.kind }}. It isn't posted yet.</span>
            </span>
            <button type="button" class="fs-close" @click="fullPreview = false">
              Close <kbd>Esc</kbd>
            </button>
          </header>
          <div class="fs-page">
            <ShopHero v-if="editor.type === 'banner'" preview :banners="[bannerPreview]" :settings="{ autoplay: false }" />
            <template v-else-if="form.kind === 'announcement'">
              <div class="bc-container fs-announce">
                <AnnouncementBar preview :items="[previewItem]" :settings="bar.values" />
              </div>
              <ShopHero preview :banners="liveBanners" :settings="{ autoplay: false }" />
            </template>
            <div v-else class="bc-container fs-split">
              <div class="fs-ghost" aria-hidden="true">
                <span class="fs-ghost-title">Shop by occasion</span>
                <span class="fs-ghost-row"><i v-for="n in 5" :key="n"></i></span>
              </div>
              <PromoCard preview :promotions="[previewItem]" />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete confirm -->
    <Transition name="fade">
      <div v-if="deleting.item" class="dialog-backdrop" @click.self="deleting.item = null">
        <div class="dialog" role="dialog" aria-modal="true">
          <h3>Delete “{{ deleting.label }}”?</h3>
          <p>It disappears from the website straight away. This can't be undone.</p>
          <div class="dialog-actions">
            <button type="button" class="ghost-btn" :disabled="deleting.busy" @click="deleting.item = null">Cancel</button>
            <button type="button" class="primary-btn danger" :disabled="deleting.busy" @click="doDelete">{{ deleting.busy ? "Deleting…" : "Delete" }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { toast } from "@/utils/snackbar";
import { useSidebarState } from "../../composables/useSidebarState";
import AdminSidebar from "../../layouts/Sidebar/AdminSidebar.vue";
import LinkPicker from "./components/LinkPicker.vue";
import AnnouncementBar from "../../components/site/AnnouncementBar.vue";
import PromoCard from "../../components/shop/PromoCard.vue";
import ShopHero from "../../components/shop/ShopHero.vue";
import ProductTag from "../../components/shop/ProductTag.vue";
import { ANNOUNCEMENT_TONES } from "../../constants/announcementTones";
import "../../styles/bloomcraft.css"; // shop look for the review preview
import { articleBlocks } from "../../utils/articleBlocks";
import api from "../../plugins/axios";

const { toggleMobile } = useSidebarState();
const route = useRoute();
const router = useRouter();

const SkeletonRows = defineComponent({
  props: { rows: { type: Number, default: 4 } },
  setup: (props) => () => h("div", { class: "sk-rows" }, Array.from({ length: props.rows }, (_, i) => h("div", { key: i, class: "sk sk-row" }))),
});

const svg = (d) => `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${d}</svg>`;
const TABS = [
  { key: "banners", label: "Banners", icon: svg('<rect x="3" y="4" width="18" height="12" rx="2"/><line x1="7" y1="20" x2="17" y2="20"/>') },
  { key: "featured", label: "Featured products", icon: svg('<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>') },
  { key: "journal", label: "Journal", icon: svg('<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>') },
  { key: "announcements", label: "Announcements & promos", icon: svg('<path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>') },
  { key: "tags", label: "Product tags", icon: svg('<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/>') },
];
const STATE = { showing: "Showing", scheduled: "Scheduled", ended: "Ended", off: "Off" };
const ARTICLE_STATE = { draft: "Draft", scheduled: "Scheduled", published: "Published" };
const ARTICLE_FILTERS = [
  { value: "", label: "All" },
  { value: "published", label: "Published" },
  { value: "scheduled", label: "Scheduled" },
  { value: "draft", label: "Drafts" },
];
const ANNOUNCEMENT_GROUPS = [
  { kind: "announcement", title: "Announcements", hint: "A slim bar at the top of the shop. Customers can dismiss each one." },
  { kind: "promotion", title: "Promotions", hint: "Cards under the shop banner, with an optional promo code to copy." },
];
// Colour picker shows each colour's icon (the same one the shop's bar shows)
const TONES = ANNOUNCEMENT_TONES;
const toneOf = (value) => TONES.find((t) => t.value === value) ?? TONES[0];
const toneStyle = (value) => ({ "--swatch": toneOf(value).swatch, "--swatch-ink": toneOf(value).ink });
const PROMO_ICON = '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><path d="M7.5 7.5h.01"/>';
const tab = ref(TABS.some((t) => t.key === route.query.tab) ? route.query.tab : "banners");
const loading = reactive({ banners: false, featured: false, search: false, articles: false, announcements: false });
const saving = ref(false);
const overview = ref(null);
const linkOptions = ref({});

const banners = ref([]);
const featured = ref([]);
const searchResults = ref([]);
const productSearch = ref("");
const articles = ref([]);
const articleCategories = ref({ care: "Flower care", guide: "Seasonal guide", trends: "Trends", stories: "Vendor stories", news: "News" });
const articleFilter = ref("");
const announcements = ref([]);

const badge = (key) => {
  const o = overview.value;
  if (!o) return null;
  return { banners: o.banners.showing, featured: o.featured, journal: o.articles.published, announcements: o.announcements.showing + o.announcements.promotions }[key] || null;
};

// ── Loading ──────────────────────────────────────────────────────
const get = async (key, url, target, params) => {
  loading[key] = true;
  try {
    const { data } = await api.get(url, { params });
    target.value = data.data;
    return data;
  } catch {
    toast.error("Something couldn't be loaded. Try refreshing.");
  } finally {
    loading[key] = false;
  }
};

const fetchOverview = async () => {
  try {
    overview.value = (await api.get("/admin/content/overview")).data.data;
  } catch {
    // badges are optional
  }
};
const fetchLinkOptions = async () => {
  try {
    linkOptions.value = (await api.get("/admin/content/link-options")).data.data;
  } catch {
    // the picker just has fewer choices
  }
};
const fetchBanners = () => {
  if (!slideshowLoaded.value) fetchSlideshow();
  return get("banners", "/admin/content/banners", banners);
};

// ── Slideshow settings ───────────────────────────────────────────
const SLIDESHOW_TOGGLES = [
  { key: "autoplay", label: "Play automatically", hint: "Move to the next banner on its own." },
  { key: "pause_on_hover", label: "Pause on hover", hint: "Hold the banner while the pointer is over it.", needsAutoplay: true },
  { key: "show_arrows", label: "Show arrows", hint: "Previous and next buttons on the sides." },
  { key: "show_dots", label: "Show dots", hint: "One dot per banner along the bottom." },
];
/** A small settings form: load, edit, undo, save. Used by the slideshow and the announcement bar. */
const settingsForm = ({ url, defaults, noun, savedMessage }) => {
  const values = reactive({ ...defaults });
  const saved = ref({ ...defaults });
  const loaded = ref(false);
  const saving = ref(false);
  const dirty = computed(() => Object.keys(defaults).some((k) => values[k] !== saved.value[k]));
  const apply = (data) => {
    saved.value = { ...defaults, ...data };
    Object.assign(values, saved.value);
  };
  const reset = () => Object.assign(values, saved.value);
  const load = async () => {
    try {
      apply((await api.get(url)).data.data);
    } catch {
      toast.error(`${noun} settings couldn't be loaded. Try refreshing.`);
    } finally {
      loaded.value = true;
    }
  };
  const save = async () => {
    const seconds = Math.round(Number(values.seconds));
    if (!Number.isFinite(seconds) || seconds < 3 || seconds > 30) {
      toast.error("Pick between 3 and 30 seconds.");
      return;
    }
    saving.value = true;
    try {
      const { data } = await api.put(url, { ...values, seconds });
      apply(data.data);
      toast.success(savedMessage);
    } catch (e) {
      toast.error(firstError(e));
    } finally {
      saving.value = false;
    }
  };
  return { values, loaded, saving, dirty, reset, load, save };
};

const {
  values: slideshow, loaded: slideshowLoaded, saving: slideshowSaving, dirty: slideshowDirty,
  reset: resetSlideshow, load: fetchSlideshow, save: saveSlideshow,
} = settingsForm({
  url: "/admin/content/banners/settings",
  defaults: { seconds: 6, autoplay: true, pause_on_hover: true, show_arrows: true, show_dots: true },
  noun: "Slideshow",
  savedMessage: "Slideshow settings saved. The shop uses them straight away.",
});

// ── Announcement bar settings ────────────────────────────────────
const BAR_TOGGLES = [
  { key: "autoplay", label: "Play automatically", hint: "Move to the next announcement on its own." },
  { key: "pause_on_hover", label: "Pause on hover", hint: "Hold the announcement while the pointer is over it.", needsAutoplay: true },
  { key: "show_arrows", label: "Show arrows", hint: "‹ › buttons to step through announcements." },
  { key: "dismissible", label: "Customers can close it", hint: "Turn off for notices everyone must see." },
];
const DISMISS_FOR = [
  { value: "forever", label: "For good" },
  { value: "1", label: "1 day" },
  { value: "7", label: "7 days" },
];
const bar = reactive(settingsForm({
  url: "/admin/content/announcements/settings",
  defaults: { seconds: 7, autoplay: true, pause_on_hover: true, show_arrows: true, dismissible: true, dismiss_for: "forever" },
  noun: "Announcement bar",
  savedMessage: "Announcement bar settings saved. The shop uses them straight away.",
}));
const fetchFeatured = () => get("featured", "/admin/content/featured", featured);
const fetchArticles = async () => {
  const data = await get("articles", "/admin/content/articles", articles, { status: articleFilter.value || undefined });
  if (data?.categories) articleCategories.value = data.categories;
};
const fetchAnnouncements = () => {
  if (!bar.loaded) bar.load();
  return get("announcements", "/admin/content/announcements", announcements);
};
let searchTimer = null;
const searchProducts = async () => {
  loading.search = true;
  try {
    searchResults.value = (await api.get("/admin/content/featured/search", { params: { q: productSearch.value.trim() || undefined } })).data.data;
  } catch {
    searchResults.value = [];
  } finally {
    loading.search = false;
  }
};
watch(productSearch, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(searchProducts, 300);
});
watch(articleFilter, fetchArticles);

const loadTab = (key) => {
  if (key === "banners") fetchBanners();
  if (key === "featured") {
    fetchFeatured();
    searchProducts();
  }
  if (key === "journal") fetchArticles();
  if (key === "announcements") fetchAnnouncements();
  if (key === "tags") loadTagRules();
};
// ── Product tags ─────────────────────────────────────────────────
// One card per tag: its switch, the numbers behind it, and how many products get it
const TAG_RULES = [
  { tag: "sale", name: "Sale", enable: "sale_enabled", desc: "Products with a discounted price.", fields: [
    { key: "sale_min_percent", label: "Discount of at least", suffix: "%", min: 1, max: 90 },
  ] },
  { tag: "best_seller", name: "Best seller", enable: "best_enabled", desc: "The products that sell the most. They are also the shop's Top Selling Flowers row.", fields: [
    { key: "best_top", label: "Top", suffix: "products", min: 1, max: 100 },
    { key: "best_days", label: "Sales in the last", suffix: "days", min: 7, max: 365 },
    { key: "best_min_sold", label: "At least", suffix: "sold", min: 1, max: 1000 },
  ] },
  { tag: "trending", name: "Trending", enable: "trend_enabled", desc: "Products getting a lot of interest right now.", note: "Interest score: each order counts 3, each add to cart 2, each heart 1.", fields: [
    { key: "trend_top", label: "Top", suffix: "products", min: 1, max: 100 },
    { key: "trend_days", label: "Interest in the last", suffix: "days", min: 1, max: 30 },
    { key: "trend_min_score", label: "Score of at least", suffix: "points", min: 1, max: 1000 },
  ] },
  { tag: "popular", name: "Popular", enable: "popular_enabled", desc: "Products customers often look at or heart.", note: "Score: each product page view counts 1, each heart 3.", fields: [
    { key: "popular_top", label: "Top", suffix: "products", min: 1, max: 100 },
    { key: "popular_days", label: "Views in the last", suffix: "days", min: 1, max: 90 },
    { key: "popular_min_score", label: "Score of at least", suffix: "points", min: 1, max: 100000 },
  ] },
  { tag: "new_arrival", name: "New arrival", enable: "new_enabled", desc: "Products shops added recently.", fields: [
    { key: "new_days", label: "Added in the last", suffix: "days", min: 1, max: 90 },
    { key: "new_max", label: "At most", suffix: "products", min: 1, max: 100 },
  ] },
  { tag: "top_rated", name: "Top rated", enable: "rated_enabled", desc: "Products customers rate highly.", fields: [
    { key: "rated_min_rating", label: "Rating of at least", suffix: "★", min: 1, max: 5, step: 0.1 },
    { key: "rated_min_reviews", label: "From at least", suffix: "reviews", min: 1, max: 1000 },
    { key: "rated_max", label: "At most", suffix: "products", min: 1, max: 100 },
  ] },
  { tag: "recommended", name: "Recommended", enable: "recommended_enabled", desc: "The products you pick under Featured products.", fields: [] },
  { tag: "seasonal", name: "Seasonal", enable: "seasonal_enabled", desc: "Products a shop set to a season rather than all year.", fields: [] },
];
const TAG_ORDER_DEFAULT = [
  "out_of_stock", "sale", "best_seller", "trending", "popular", "new_arrival",
  "top_rated", "recommended", "seasonal", "you_may_like",
];
// Tags with no switch: always shown when they apply
const ALWAYS_ON = {
  out_of_stock: "When it's sold out",
  you_may_like: "On the “You may also like” row",
};
const TAG_DEFAULTS = {
  order: TAG_ORDER_DEFAULT, rotate_hourly: true, sale_enabled: true, sale_min_percent: 5,
  best_enabled: true, best_top: 20, best_days: 365, best_min_sold: 1,
  new_enabled: true, new_days: 14, new_max: 12,
  trend_enabled: true, trend_top: 8, trend_days: 7, trend_min_score: 3,
  popular_enabled: true, popular_top: 10, popular_days: 14, popular_min_score: 10,
  rated_enabled: true, rated_min_rating: 4.5, rated_min_reviews: 3, rated_max: 20,
  recommended_enabled: true, seasonal_enabled: true,
};
const tagRules = reactive({ ...TAG_DEFAULTS, order: [...TAG_ORDER_DEFAULT] });
const savedTagRules = ref({ ...TAG_DEFAULTS, order: [...TAG_ORDER_DEFAULT] });
const tagCounts = ref({});
const tagsLoaded = ref(false);
const tagsSaving = ref(false);
const sameValue = (a, b) => (Array.isArray(a) ? a.join() === (b || []).join() : a === b);
const tagsDirty = computed(() => Object.keys(TAG_DEFAULTS).some((k) => !sameValue(tagRules[k], savedTagRules.value[k])));
// The form gets its own copy of the order, so dragging never changes the saved one
const copyRules = (rules) => ({ ...rules, order: [...(rules.order || TAG_ORDER_DEFAULT)] });

const ruleOf = (tag) => TAG_RULES.find((r) => r.tag === tag);
const tagName = (tag) => ruleOf(tag)?.name || ALWAYS_ON[tag] && tag.replace(/_/g, " ") || tag;
const tagIsOn = (tag) => (ALWAYS_ON[tag] ? true : !!tagRules[ruleOf(tag)?.enable]);
const tagNote = (tag) => ALWAYS_ON[tag] || (tagIsOn(tag) ? countText(ruleOf(tag)) : "Off, never shown");

function moveTag(i, step) {
  const list = tagRules.order;
  const to = i + step;
  if (to < 0 || to >= list.length) return;
  list.splice(to, 0, list.splice(i, 1)[0]);
  nextTick(() => rankEls[list[to]]?.querySelector(".rank-handle")?.focus());
}

// Dragging: works with a mouse, a pen or a finger
const rankEls = {};
const dragTag = ref(null);
function startTagDrag(event, tag) {
  if (event.button !== undefined && event.button !== 0) return;
  event.preventDefault();
  dragTag.value = tag;
  const onMove = (e) => {
    const list = tagRules.order;
    const from = list.indexOf(dragTag.value);
    // The first item whose middle is below the pointer is where it goes
    let to = list.length - 1;
    for (let i = 0; i < list.length; i++) {
      const box = rankEls[list[i]]?.getBoundingClientRect();
      if (box && e.clientY < box.top + box.height / 2) {
        to = i > from ? i - 1 : i;
        break;
      }
    }
    if (to !== from) list.splice(to, 0, list.splice(from, 1)[0]);
  };
  const onUp = () => {
    dragTag.value = null;
    window.removeEventListener("pointermove", onMove);
    window.removeEventListener("pointerup", onUp);
    window.removeEventListener("pointercancel", onUp);
  };
  window.addEventListener("pointermove", onMove);
  window.addEventListener("pointerup", onUp);
  window.addEventListener("pointercancel", onUp);
}
const countText = (rule) => {
  if (!tagRules[rule.enable]) return "Off";
  const n = tagCounts.value[rule.tag] ?? 0;
  return n + (n === 1 ? " product now" : " products now");
};
const applyTagRules = (data) => {
  savedTagRules.value = copyRules({ ...TAG_DEFAULTS, ...(data.rules || {}) });
  Object.assign(tagRules, copyRules(savedTagRules.value));
  if (data.counts) tagCounts.value = data.counts;
};
const resetTagRules = () => Object.assign(tagRules, copyRules(savedTagRules.value));
async function loadTagRules() {
  try {
    applyTagRules((await api.get("/admin/content/product-tags")).data.data);
  } catch {
    toast.error("The tag rules couldn't be loaded. Try refreshing.");
  } finally {
    tagsLoaded.value = true;
  }
}
// The counts follow the numbers as they change, before saving
let tagPreviewTimer = 0;
let tagPreviewSeq = 0;
watch(() => Object.keys(TAG_DEFAULTS).filter((k) => k !== "order").map((k) => tagRules[k]).join("|"), () => {
  if (!tagsLoaded.value) return;
  clearTimeout(tagPreviewTimer);
  tagPreviewTimer = setTimeout(async () => {
    const mine = ++tagPreviewSeq;
    try {
      const { data } = await api.post("/admin/content/product-tags/preview", { ...tagRules });
      if (mine === tagPreviewSeq) tagCounts.value = data.data.counts;
    } catch {
      // a number out of range: the save shows why
    }
  }, 450);
});
async function saveTagRules() {
  tagsSaving.value = true;
  try {
    const { data } = await api.put("/admin/content/product-tags", { ...tagRules });
    applyTagRules(data.data);
    toast.success(data.message || "Product tag rules saved.");
  } catch (e) {
    toast.error(firstError(e));
  } finally {
    tagsSaving.value = false;
  }
}

const setTab = (key) => {
  tab.value = key;
  router.replace({ query: { ...route.query, tab: key } });
  loadTab(key);
};

// ── Ordering ─────────────────────────────────────────────────────
const saveOrder = async (type, list, idKey = "id") => {
  try {
    await api.post(`/admin/content/${type}/reorder`, { ids: list.map((x) => x[idKey]) });
  } catch {
    toast.error("The new order couldn't be saved.");
  }
};
const move = (type, list, index, delta, idKey) => {
  const target = index + delta;
  [list[index], list[target]] = [list[target], list[index]];
  saveOrder(type, list, idKey);
};
const announcementsOf = (kind) => announcements.value.filter((a) => a.kind === kind);
const moveAnnouncement = (kind, index, delta) => {
  const group = announcementsOf(kind);
  const target = index + delta;
  [group[index], group[target]] = [group[target], group[index]];
  const others = announcements.value.filter((a) => a.kind !== kind);
  announcements.value = kind === "announcement" ? [...group, ...others] : [...others, ...group];
  saveOrder("announcements", announcements.value);
};

// ── Featured ─────────────────────────────────────────────────────
const addFeatured = async (p) => {
  try {
    const { data } = await api.post("/admin/content/featured", { product_id: p.id });
    toast.success(data.message);
    await Promise.all([fetchFeatured(), searchProducts(), fetchOverview()]);
  } catch (e) {
    toast.error(e.response?.data?.message || "It couldn't be added.");
  }
};
const removeFeatured = async (p) => {
  try {
    await api.delete(`/admin/content/featured/${p.featured_id}`);
    featured.value = featured.value.filter((x) => x.featured_id !== p.featured_id);
    searchProducts();
    fetchOverview();
  } catch {
    toast.error("It couldn't be removed.");
  }
};

// ── Editor ───────────────────────────────────────────────────────
const editor = reactive({ type: null, item: null, error: "", reviewing: false });

// Review step (announcements + promotions): the real shop components, fed the form
const previewButton = computed(() => form.link_label?.trim() || (form.kind === "promotion" ? "Shop the offer" : "Learn more"));
const previewItem = computed(() => ({
  id: "preview",
  kind: form.kind,
  title: form.title?.trim() || "Your title",
  message: form.message?.trim() || "",
  tone: form.tone,
  promo_code: form.kind === "promotion" ? form.promo_code?.trim() || "" : "",
  link_type: form.link_type || "",
  link_label: previewButton.value,
  image_url: form.kind === "promotion" ? imagePreview.value : null,
  ends_at: form.ends_at || null,
}));
// Full-screen preview (banners, and the review step)
const fullPreview = ref(false);
const fsEl = ref(null);
const bannerPreview = computed(() => ({
  id: "preview",
  eyebrow: form.eyebrow?.trim() || "",
  title: form.title?.trim() || "Your headline",
  subtitle: form.subtitle?.trim() || "",
  button_label: form.button_label?.trim() || "",
  link_type: form.link_type || "",
  image_url: imagePreview.value,
}));
// behind an announcement: the banners that are showing now
const liveBanners = computed(() => banners.value.filter((b) => b.state === "showing"));
const onFsKey = (e) => {
  if (e.key === "Escape") {
    e.stopPropagation();
    fullPreview.value = false;
  }
};
watch(fullPreview, async (open) => {
  if (open) {
    window.addEventListener("keydown", onFsKey, true);
    await nextTick();
    fsEl.value?.focus();
  } else {
    window.removeEventListener("keydown", onFsKey, true);
  }
});
watch(() => editor.type, (type) => !type && (fullPreview.value = false));
onBeforeUnmount(() => window.removeEventListener("keydown", onFsKey, true));
const reviewWhen = computed(() => {
  const start = form.starts_at ? formatDateTime(form.starts_at) : null;
  const end = form.ends_at ? formatDateTime(form.ends_at) : null;
  if (start && end) return `From ${start} until ${end}`;
  if (start) return `From ${start}, no end date`;
  if (end) return `Straight away, until ${end}`;
  return "Straight away, no end date";
});
const form = reactive({});
const imageFile = ref(null);
const imagePreview = ref(null);
const removeImage = ref(false);
const previewBody = ref(false);

const toLocalInput = (iso) => {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const openEditor = (type, item, values, image) => {
  Object.keys(form).forEach((k) => delete form[k]);
  Object.assign(form, values);
  Object.assign(editor, { type, item, error: "", reviewing: false });
  imageFile.value = null;
  imagePreview.value = image ?? null;
  removeImage.value = false;
  previewBody.value = false;
  if (!Object.keys(linkOptions.value).length) fetchLinkOptions();
};

const editBanner = (b = null) =>
  openEditor("banner", b, {
    eyebrow: b?.eyebrow ?? "", title: b?.title ?? "", subtitle: b?.subtitle ?? "", button_label: b?.button_label ?? "",
    link_type: b?.link_type ?? "products", link_value: b?.link_value ?? "",
    starts_at: toLocalInput(b?.starts_at), ends_at: toLocalInput(b?.ends_at), is_active: b ? b.is_active : true,
  }, b?.image_url);

const editAnnouncement = (a = null, kind = "announcement") =>
  openEditor("announcement", a, {
    kind: a?.kind ?? kind, title: a?.title ?? "", message: a?.message ?? "", promo_code: a?.promo_code ?? "",
    tone: a?.tone ?? "brand", link_label: a?.link_label ?? "", link_type: a?.link_type ?? "", link_value: a?.link_value ?? "",
    starts_at: toLocalInput(a?.starts_at), ends_at: toLocalInput(a?.ends_at), is_active: a ? a.is_active : true,
  }, a?.image_url);

const editArticle = async (a = null) => {
  openEditor("article", a, { title: a?.title ?? "", category: a?.category ?? "care", excerpt: a?.excerpt ?? "", body: "", is_featured: a?.is_featured ?? false, publish_at: "" }, a?.cover_url);
  if (a) {
    try {
      const { data } = await api.get(`/admin/content/articles/${a.id}`);
      if (editor.item?.id === a.id) {
        form.body = data.data.body;
        editor.item = data.data;
      }
    } catch {
      editor.error = "The article couldn't be loaded.";
    }
  }
};

const editorTitle = computed(() => {
  const isNew = !editor.item;
  if (editor.type === "banner") return isNew ? "New banner" : "Edit banner";
  if (editor.type === "article") return isNew ? "New article" : "Edit article";
  if (editor.reviewing) return `Review ${form.kind === "promotion" ? "promotion" : "announcement"}`;
  return `${isNew ? "New" : "Edit"} ${form.kind === "promotion" ? "promotion" : "announcement"}`;
});

const closeEditor = () => {
  if (!saving.value) editor.type = null;
};

const pickImage = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) {
    toast.error("That image is over 5 MB.");
    return;
  }
  imageFile.value = file;
  removeImage.value = false;
  imagePreview.value = URL.createObjectURL(file);
};
const clearImage = () => {
  imageFile.value = null;
  imagePreview.value = null;
  removeImage.value = true;
};

const buildForm = (fields, imageKey) => {
  const fd = new FormData();
  for (const [key, value] of Object.entries(fields)) {
    if (value === null || value === undefined) continue;
    fd.append(key, typeof value === "boolean" ? (value ? "1" : "0") : value);
  }
  if (imageFile.value) fd.append(imageKey, imageFile.value);
  return fd;
};

const firstError = (e) => {
  const errors = e.response?.data?.errors;
  return errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "It couldn't be saved.";
};

const saveEditor = async () => {
  editor.error = "";
  if (editor.type === "banner" && !imagePreview.value) {
    editor.error = "Choose a banner image.";
    return;
  }
  // Announcements and promotions are checked on a preview before they go up
  if (editor.type === "announcement" && !editor.reviewing) {
    if (!form.title.trim()) {
      editor.error = "Add a title first.";
      return;
    }
    if (form.starts_at && form.ends_at && new Date(form.ends_at) <= new Date(form.starts_at)) {
      editor.error = "The end must be after the start.";
      return;
    }
    editor.reviewing = true;
    return;
  }
  saving.value = true;
  try {
    if (editor.type === "banner") {
      const { link_type, link_value, ...rest } = form;
      const fd = buildForm({ ...rest, link_type, link_value: link_type === "products" ? "" : link_value }, "image");
      await api.post(editor.item ? `/admin/content/banners/${editor.item.id}` : "/admin/content/banners", fd);
      await fetchBanners();
    } else if (editor.type === "announcement") {
      const fd = buildForm({ ...form, promo_code: form.kind === "promotion" ? form.promo_code : "", link_type: form.link_type || "", link_value: form.link_type && form.link_type !== "products" ? form.link_value : "" }, "image");
      await api.post(editor.item ? `/admin/content/announcements/${editor.item.id}` : "/admin/content/announcements", fd);
      await fetchAnnouncements();
    } else {
      const { publish_at, ...rest } = form;
      const fd = buildForm({ ...rest, remove_cover: removeImage.value }, "cover");
      const { data } = await api.post(editor.item ? `/admin/content/articles/${editor.item.id}` : "/admin/content/articles", fd);
      editor.item = data.data;
      await fetchArticles();
      toast.success("Saved.");
      fetchOverview();
      return data.data;
    }
    toast.success("Saved. It's on the website now if it's switched on.");
    editor.type = null;
    fetchOverview();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

const saveAndPublish = async () => {
  if (!form.title.trim() || form.body.trim().length < 20) {
    editor.error = "Add a title and at least a few sentences before publishing.";
    return;
  }
  const article = await saveEditor();
  if (!article) return;
  saving.value = true;
  try {
    const { data } = await api.post(`/admin/content/articles/${article.id}/publish`, { published_at: form.publish_at || undefined });
    toast.success(data.message);
    editor.type = null;
    fetchArticles();
    fetchOverview();
    fetchLinkOptions();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

const unpublish = async () => {
  saving.value = true;
  try {
    const { data } = await api.post(`/admin/content/articles/${editor.item.id}/unpublish`);
    editor.item = data.data;
    toast.success(data.message);
    fetchArticles();
    fetchOverview();
  } catch (e) {
    editor.error = firstError(e);
  } finally {
    saving.value = false;
  }
};

// ── Delete ───────────────────────────────────────────────────────
const deleting = reactive({ type: null, item: null, label: "", busy: false });
const confirmDelete = (type, item, label) => Object.assign(deleting, { type, item, label, busy: false });
const doDelete = async () => {
  deleting.busy = true;
  try {
    await api.delete(`/admin/content/${deleting.type}/${deleting.item.id}`);
    toast.success("Deleted.");
    if (deleting.type === "banners") fetchBanners();
    if (deleting.type === "announcements") fetchAnnouncements();
    if (deleting.type === "articles") {
      fetchArticles();
      editor.type = null;
    }
    fetchOverview();
    deleting.item = null;
  } catch {
    toast.error("It couldn't be deleted.");
  } finally {
    deleting.busy = false;
  }
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const money = (v) => peso.format(Number(v || 0));
const formatDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—";
const timeAgo = (v) => {
  const days = Math.floor((Date.now() - new Date(v)) / 86_400_000);
  return days < 1 ? "today" : days === 1 ? "yesterday" : `${days} days ago`;
};
const scheduleText = (item) => {
  if (item.starts_at && item.state === "scheduled") return `Starts ${formatDateTime(item.starts_at)}`;
  if (item.ends_at) return `${item.state === "ended" ? "Ended" : "Until"} ${formatDateTime(item.ends_at)}`;
  return "No end date";
};
const describeLink = (item) => {
  const find = (list) => (linkOptions.value[list] ?? []).find((o) => o.value === item.link_value)?.label ?? item.link_value;
  return (
    {
      products: "All products",
      category: `Category: ${find("categories")}`,
      occasion: `Occasion: ${find("occasions")}`,
      store: `Store: ${find("stores")}`,
      article: `Article: ${find("articles")}`,
      url: item.link_value,
    }[item.link_type] ?? "—"
  );
};

onMounted(() => {
  fetchOverview();
  fetchLinkOptions();
  if (route.query.tab === "settings") return router.replace({ path: "/admin/settings", query: { tab: "website" } });
  if (route.query.tab === "taxonomies") return router.replace("/admin/categories");
  loadTab(tab.value);
});
</script>

<style scoped>
* {
  font-family: "Poppins", "Segoe UI", sans-serif;
  box-sizing: border-box;
}
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: var(--a-subtle);
}
.main-content {
  margin-left: 240px;
  flex: 1;
  min-width: 0;
  min-height: 100vh;
}
.page-body {
  padding: 18px 28px 40px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Header */
.content-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 20px 28px;
  background: var(--a-surface);
  border-bottom: 1px solid var(--a-border);
  position: sticky;
  top: 0;
  z-index: 100;
}
.header-left,
.header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}
.mobile-toggle {
  display: none;
  background: transparent;
  border: none;
  color: var(--a-muted);
  cursor: pointer;
  padding: 4px;
}
.page-title {
  font-size: 22px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0;
  line-height: 1.2;
}
.page-subtitle {
  font-size: 12px;
  color: var(--a-faint);
  margin: 2px 0 0;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  padding-bottom: 2px;
}
.tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 14px;
  border-radius: 10px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
}
.tab.active {
  background: var(--a-accent-soft-2);
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.tab-icon {
  display: inline-flex;
}
.tab-badge {
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 10px;
  background: var(--a-green-soft);
  color: var(--a-green-text);
}

/* Buttons */
.ghost-btn,
.primary-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 9px;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
  text-decoration: none;
}
.ghost-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-text-2);
}
.ghost-btn:hover:not(:disabled) {
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.ghost-btn.danger-text {
  color: var(--a-red-text);
}
.primary-btn {
  border: none;
  background: var(--a-accent);
  color: var(--a-accent-ink);
}
.primary-btn:hover:not(:disabled) {
  background: var(--a-accent-deep);
}
.primary-btn.danger {
  background: #dc2626;
}
.ghost-btn:disabled,
.primary-btn:disabled,
.mini-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.mini-btn {
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  border-radius: 8px;
  padding: 6px 11px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
  cursor: pointer;
  white-space: nowrap;
}
.mini-btn:hover:not(:disabled) {
  border-color: var(--a-accent-muted);
  color: var(--a-accent-text);
}
.mini-btn.danger { color: var(--a-red-text); }
.mini-btn.danger:hover:not(:disabled) { background: var(--a-red-soft); border-color: var(--a-red-border); color: var(--a-red-text); }
.mini-btn.ok { color: var(--a-green-text); }
.mini-btn.ok:hover:not(:disabled) { background: #16a34a; border-color: #16a34a; color: #fff; }
.link-btn {
  border: none;
  background: none;
  color: var(--a-accent-text);
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  text-decoration: none;
}
.icon-btn {
  width: 34px;
  height: 34px;
  border-radius: 9px;
  border: 1px solid var(--a-border);
  background: var(--a-surface);
  color: var(--a-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Panels */
.panel {
  background: var(--a-surface);
  border: 1px solid var(--a-border);
  border-radius: 14px;
  padding: 18px 20px;
  min-width: 0;
}
.panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.panel-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0;
}
.panel-sub {
  font-size: 12px;
  color: var(--a-faint);
  margin: 3px 0 0;
  max-width: 640px;
}
.split {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.split.even {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.count-pill {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 20px;
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}
.empty {
  font-size: 13px;
  color: var(--a-faint);
  text-align: center;
  padding: 32px 0;
  margin: 0;
}
.empty.small {
  padding: 18px 0;
}

/* Skeleton */
.sk {
  border-radius: 10px;
  background: linear-gradient(100deg, var(--a-skel) 30%, var(--a-skel-shine) 50%, var(--a-skel) 70%);
  background-size: 300% 100%;
  animation: shimmer 1.6s ease-in-out infinite;
}
.sk-rows {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.sk-row {
  height: 64px;
}
@keyframes shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}

/* Lists */
.media-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.media-list li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  background: var(--a-subtle);
}
.media-list li.off {
  opacity: 0.65;
}
.media-list li.clickable {
  cursor: pointer;
}
.media-list li.clickable:hover,
.media-list li.clickable:focus-visible {
  border-color: var(--a-accent-muted);
  background: var(--a-subtle);
  outline: none;
}
.media-list.compact li {
  padding: 8px 10px;
}
.thumb {
  width: 56px;
  height: 56px;
  border-radius: 10px;
  object-fit: cover;
  flex-shrink: 0;
  background: var(--a-subtle-2);
}
.thumb.wide {
  width: 104px;
}
.thumb.placeholder {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  color: #c084fc;
}
.media-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.media-side {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
  text-align: right;
}
.row-actions {
  display: flex;
  gap: 6px;
}
.strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--a-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sub {
  font-size: 11.5px;
  color: var(--a-faint);
  overflow: hidden;
  text-overflow: ellipsis;
}
.sub.warn {
  color: var(--a-amber-text);
}
.sub.note {
  margin-top: 14px;
  display: block;
}
.star {
  color: #f59e0b;
  margin-left: 6px;
}
.code {
  align-self: flex-start;
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 11px;
  font-weight: 600;
  padding: 1px 7px;
  border-radius: 6px;
  border: 1px dashed #c084fc;
  color: var(--a-purple-text);
}
.reorder {
  display: flex;
  flex-direction: column;
}
.reorder button {
  border: none;
  background: none;
  font-size: 8px;
  padding: 2px 3px;
  color: var(--a-faint);
  cursor: pointer;
}
.reorder button:disabled {
  opacity: 0.3;
  cursor: default;
}
.pill {
  display: inline-block;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  white-space: nowrap;
  background: var(--a-subtle-2);
  color: var(--a-muted);
}
.state-showing,
.article-published { background: var(--a-green-soft); color: var(--a-green-text); }
.state-scheduled,
.article-scheduled { background: var(--a-blue-soft); color: var(--a-blue-text); }
.state-ended,
.state-off,
.article-draft { background: var(--a-subtle-2); color: var(--a-muted); }
/* Announcement rows: the bar's own icon in its own colour */
.tone-dot {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  flex-shrink: 0;
  background: var(--swatch);
  color: var(--swatch-ink);
  box-shadow: inset 0 0 0 1px var(--a-border);
}
.tone-dot svg,
.tone-btn svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.promo-dot {
  --swatch: var(--a-green-soft);
  --swatch-ink: var(--a-green-text);
}

.seg {
  display: inline-flex;
  padding: 3px;
  background: var(--a-subtle-2);
  border-radius: 9px;
  gap: 2px;
  margin-bottom: 12px;
}
.seg.wide {
  display: flex;
}
.seg.wide .seg-btn {
  flex: 1;
}
.seg.small {
  margin: 0;
}
.seg-btn {
  border: none;
  background: transparent;
  padding: 6px 12px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-muted);
  cursor: pointer;
}
.seg-btn.active {
  background: var(--a-surface);
  color: var(--a-accent-text);
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

/* Taxonomy preview */
.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.chip {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 20px;
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}

/* Forms */
.form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
}
.field.full {
  grid-column: 1 / -1;
}
.field-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--a-text-2);
}
.input {
  padding: 9px 11px;
  border: 1px solid var(--a-border);
  border-radius: 9px;
  font-size: 13px;
  color: var(--a-text);
  background: var(--a-surface);
  font-family: inherit;
  width: 100%;
}
.input:focus {
  outline: none;
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px rgba(124, 111, 247, 0.12);
}
.input.mono {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  text-transform: uppercase;
}
textarea.input {
  resize: vertical;
}
.hint {
  font-weight: 400;
  color: var(--a-faint);
  font-size: 11.5px;
}
.hint code {
  background: var(--a-subtle-2);
  padding: 0 4px;
  border-radius: 4px;
  font-size: 11px;
}
.form-error {
  color: var(--a-red-text);
  font-size: 12.5px;
  margin: 10px 0 0;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
.search-wrap {
  position: relative;
  display: flex;
  align-items: center;
  margin: 12px 0;
}
.search-ico {
  position: absolute;
  left: 11px;
  color: var(--a-faint);
}
.search-input {
  padding-left: 32px;
  background: var(--a-subtle);
}
.two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  align-items: end;
}
.check {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--a-text-2);
  cursor: pointer;
  padding-bottom: 9px;
}
.check input {
  accent-color: var(--a-accent);
}

/* Slideshow settings */
/* Product tags */
.tag-order {
  display: grid;
  gap: 10px;
  margin-bottom: 14px;
  padding: 14px 16px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  background: var(--a-subtle);
}
.tag-rotate {
  min-height: 0;
  padding: 10px 12px;
  border: 1px solid var(--a-border);
  border-radius: 10px;
  background: var(--a-surface);
}
.tag-order-head {
  display: grid;
  gap: 2px;
}
.tag-rank {
  display: grid;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.rank-item {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 4px 8px 4px 4px;
  border: 1px solid var(--a-border);
  border-radius: 10px;
  background: var(--a-surface);
  transition: box-shadow 0.15s ease, border-color 0.15s ease;
}
.rank-item.dragging {
  position: relative;
  z-index: 1;
  border-color: var(--a-accent);
  box-shadow: 0 8px 22px rgba(0, 0, 0, 0.14);
}
.rank-item.off .rank-num,
.rank-item.off .rank-note {
  opacity: 0.55;
}
.rank-handle {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--a-text-2);
  cursor: grab;
  touch-action: none;
}
.rank-item.dragging .rank-handle {
  cursor: grabbing;
}
.rank-handle:hover {
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}
.rank-handle:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 1px;
}
.rank-handle svg {
  width: 18px;
  height: 18px;
  fill: currentColor;
}
.rank-num {
  min-width: 18px;
  color: var(--a-text-2);
  font-size: 12px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.rank-note {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--a-text-2);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rank-arrows {
  display: flex;
  gap: 2px;
}
.rank-arrows button {
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: var(--a-text-2);
  font-size: 11px;
  cursor: pointer;
}
.rank-arrows button:hover:not(:disabled) {
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}
.rank-arrows button:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}
.tag-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 12px;
}
.tag-card {
  display: grid;
  align-content: start;
  gap: 10px;
  padding: 14px 16px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  background: var(--a-surface);
}
.tag-card.off .tag-desc,
.tag-card.off .tag-fields {
  opacity: 0.5;
}
.tag-card-head {
  display: flex;
  align-items: center;
  gap: 10px;
}
.tag-count {
  margin-left: auto;
  color: var(--a-text-2);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}
.tag-switch {
  flex: none;
  position: relative;
  min-height: 0;
  align-items: center;
}
.tag-desc {
  margin: 0;
  color: var(--a-text);
  font-size: 13px;
}
.tag-fields {
  display: grid;
  gap: 8px;
}
.tag-field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 84px minmax(52px, auto);
  align-items: center;
  gap: 8px;
  color: var(--a-text-2);
  font-size: 12.5px;
}
.tag-num {
  padding: 7px 9px;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.tag-num:disabled {
  opacity: 0.6;
}
.tag-fixed {
  display: grid;
  gap: 6px;
  margin-top: 14px;
  padding: 12px 16px;
  border: 1px dashed var(--a-border);
  border-radius: 12px;
}
.tag-fixed .hint {
  line-height: 2;
}
.show-settings {
  border: 1px solid var(--a-border);
  background: var(--a-subtle);
  border-radius: 12px;
  padding: 14px 16px;
  margin-bottom: 14px;
}
.show-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}
.show-title {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--a-text);
  margin: 0;
}
.show-save {
  display: flex;
  align-items: center;
  gap: 8px;
}
.show-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr);
  gap: 18px;
  align-items: start;
}
.show-time {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.time-row {
  display: flex;
  align-items: center;
  gap: 12px;
}
.range {
  flex: 1;
  min-width: 0;
  accent-color: var(--a-accent);
  height: 24px;
  cursor: pointer;
}
.stepper {
  display: inline-flex;
  align-items: center;
  border: 1px solid var(--a-border);
  border-radius: 9px;
  background: var(--a-surface);
  overflow: hidden;
}
.stepper button {
  width: 36px;
  height: 36px;
  border: 0;
  background: transparent;
  color: var(--a-text-2);
  font-size: 16px;
  cursor: pointer;
}
.stepper button:hover:not(:disabled) {
  background: var(--a-accent-soft);
  color: var(--a-accent-text);
}
.stepper button:focus-visible,
.range:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}
.stepper button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.stepper output {
  min-width: 44px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
  color: var(--a-text);
  font-variant-numeric: tabular-nums;
}
.show-toggles {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px 16px;
}
.toggle {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  min-height: 44px;
}
.toggle input {
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
}
.toggle-track {
  flex: 0 0 auto;
  width: 34px;
  height: 20px;
  margin-top: 1px;
  border-radius: 20px;
  background: var(--a-subtle-2);
  border: 1px solid var(--a-border);
  position: relative;
  transition: background 0.18s ease, border-color 0.18s ease;
}
.toggle-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: var(--a-surface);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
  transition: transform 0.18s ease;
}
.toggle input:checked + .toggle-track {
  background: var(--a-accent);
  border-color: var(--a-accent);
}
.toggle input:checked + .toggle-track .toggle-thumb {
  transform: translateX(14px);
}
.toggle input:focus-visible + .toggle-track {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}
.toggle-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 13px;
  color: var(--a-text);
}
.toggle-text .strong {
  font-weight: 500;
}
/* inside a half-width panel: one column, toggles still two-up */
.show-settings.narrow .show-grid {
  grid-template-columns: minmax(0, 1fr);
}
.seg-btn:disabled {
  cursor: not-allowed;
}
.seg-btn:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 1px;
}
.toggle.muted,
.show-time.muted {
  opacity: 0.55;
}
.toggle.muted {
  cursor: not-allowed;
}
@media (max-width: 900px) {
  .show-grid,
  .show-toggles {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (prefers-reduced-motion: reduce) {
  .toggle-track,
  .toggle-thumb {
    transition: none;
  }
}
.tones {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.tone-btn {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--a-border);
  border-radius: 50%;
  background: var(--swatch);
  color: var(--swatch-ink);
  cursor: pointer;
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}
.tone-btn svg {
  width: 20px;
  height: 20px;
}
.tone-btn:hover {
  transform: translateY(-1px);
}
.tone-btn.active {
  box-shadow: 0 0 0 2px var(--a-surface), 0 0 0 4px var(--a-accent);
}
.tone-btn:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 3px;
}

/* Review before posting */
.review-intro h3 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 600;
  color: var(--a-text);
}
.review-intro p {
  margin: 0;
  font-size: 13px;
  color: var(--a-muted);
}
/* Always the shop's light look, whatever the admin theme */
.review-stage {
  margin: 0;
  padding: 16px;
  border: 1px solid var(--a-border);
  border-radius: 12px;
  background: var(--ivory);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.review-where {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.review-facts {
  margin: 0;
  display: grid;
  gap: 1px;
  border: 1px solid var(--a-border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--a-border);
}
.review-facts div {
  display: grid;
  grid-template-columns: 110px 1fr;
  gap: 12px;
  padding: 10px 14px;
  background: var(--a-surface);
  font-size: 13px;
}
.review-facts dt {
  color: var(--a-muted);
}
.review-facts dd {
  margin: 0;
  color: var(--a-text);
  overflow-wrap: anywhere;
}
.review-note {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--a-amber-soft);
  color: var(--a-amber-text);
  font-size: 13px;
}
.fs-open {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 0 12px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: var(--paper);
  color: var(--ink);
  font: 600 12px var(--font-sans);
  letter-spacing: 0;
  text-transform: none;
  cursor: pointer;
}
.fs-open:hover {
  border-color: var(--leaf-deep);
  color: var(--leaf-deep);
}
.fs-cta {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.fs-cta .ghost-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

/* Full-screen preview: a slice of the shop at real size, always light */
.fs-preview {
  position: fixed;
  inset: 0;
  z-index: 500;
  display: flex;
  flex-direction: column;
  background: var(--ivory);
  outline: none;
}
.fs-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 16px;
  background: var(--espresso);
  color: var(--paper);
  font-family: var(--font-sans);
}
.fs-label {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 4px 10px;
  min-width: 0;
  font-size: 13px;
}
.fs-label span {
  color: rgba(250, 251, 250, 0.74);
}
.fs-close {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid rgba(250, 251, 250, 0.24);
  border-radius: 999px;
  background: transparent;
  color: var(--paper);
  font: 600 13px var(--font-sans);
  cursor: pointer;
}
.fs-close:hover {
  background: rgba(250, 251, 250, 0.12);
}
.fs-close:focus-visible,
.fs-open:focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}
.fs-close kbd {
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(250, 251, 250, 0.14);
  font: 500 11px var(--font-sans);
}
.fs-page {
  flex: 1;
  overflow-y: auto;
}
.fs-announce {
  padding-top: 12px;
  padding-bottom: 12px;
}
.fs-split {
  display: grid;
  grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
  gap: 22px;
  padding-top: 40px;
  padding-bottom: 40px;
}
.fs-ghost {
  display: flex;
  flex-direction: column;
  gap: 22px;
  padding: 26px 30px;
  border: 1px dashed var(--line);
  border-radius: 16px;
  opacity: 0.6;
}
.fs-ghost-title {
  font: 500 28px var(--font-display);
  color: var(--ink);
}
.fs-ghost-row {
  display: flex;
  gap: 18px;
}
.fs-ghost-row i {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  background: var(--ivory-deep);
}
@media (max-width: 960px) {
  .fs-split {
    grid-template-columns: minmax(0, 1fr);
  }
  .fs-ghost {
    display: none;
  }
}
@media (max-width: 560px) {
  .fs-label span {
    display: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  .tone-btn {
    transition: none;
  }
}

/* Footer preview */

/* Drawer */
.drawer-backdrop,
.dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.38);
  backdrop-filter: blur(2px);
  z-index: 300;
}
.dialog-backdrop {
  z-index: 400;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.drawer {
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: min(640px, 100vw);
  background: var(--a-subtle);
  z-index: 301;
  display: flex;
  flex-direction: column;
  box-shadow: -20px 0 50px rgba(15, 23, 42, 0.18);
}
.drawer-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 20px;
  background: var(--a-surface);
  border-bottom: 1px solid var(--a-border);
}
.drawer-head h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 600;
  color: var(--a-text);
}
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 18px 20px 28px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.drawer-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  background: var(--a-surface);
  border-top: 1px solid var(--a-border);
}
.spacer {
  flex: 1;
}
.image-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.image-drop {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 150px;
  border: 1.5px dashed var(--a-accent-muted);
  border-radius: 12px;
  background: var(--a-subtle);
  color: var(--a-accent-text);
  font-size: 12.5px;
  text-align: center;
  padding: 14px;
  cursor: pointer;
  overflow: hidden;
}
.image-drop.filled {
  padding: 0;
  border-style: solid;
}
.image-drop img {
  width: 100%;
  max-height: 260px;
  object-fit: cover;
  display: block;
}
.image-drop input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.schedule {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--a-border);
}
.body-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.body-input {
  font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
  font-size: 12.5px;
  line-height: 1.6;
}
.body-preview {
  min-height: 300px;
  padding: 16px 18px;
  border: 1px solid var(--a-border);
  border-radius: 9px;
  background: var(--a-surface);
  font-family: Georgia, serif;
  font-size: 15px;
  line-height: 1.7;
  color: var(--a-text);
  font-weight: 400;
}
.body-preview h2 {
  font-size: 22px;
  margin: 16px 0 8px;
}
.body-preview h3 {
  font-size: 17px;
  margin: 14px 0 6px;
}
.body-preview blockquote {
  margin: 12px 0;
  padding-left: 14px;
  border-left: 3px solid #b86558;
  font-style: italic;
}
.publish-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px 14px;
  border-radius: 12px;
  background: var(--a-surface);
  border: 1px solid var(--a-border);
}
.publish-box > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.small-field {
  min-width: 220px;
}

/* Dialog */
.dialog {
  width: min(420px, 100%);
  background: var(--a-surface);
  border-radius: 16px;
  padding: 22px;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
}
.dialog h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 600;
  color: var(--a-text);
}
.dialog p {
  margin: 0;
  font-size: 13px;
  color: var(--a-text-2);
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 18px;
}

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.slide-enter-active,
.slide-leave-active {
  transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
  transform: translateX(100%);
}

@media (max-width: 1280px) {
  .main-content {
    margin-left: 200px;
  }
}
@media (max-width: 1100px) {
  .split,
  .split.even {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 968px) {
  .main-content {
    margin-left: 0;
  }
  .mobile-toggle {
    display: flex;
  }
}
@media (max-width: 640px) {
  .content-header {
    padding: 16px;
  }
  .page-body {
    padding: 14px 16px 32px;
  }
  .form-grid,
  .two {
    grid-template-columns: minmax(0, 1fr);
  }
  .media-list li {
    flex-wrap: wrap;
  }
  .media-side {
    align-items: flex-start;
    text-align: left;
  }
}
@media (prefers-reduced-motion: reduce) {
  .sk {
    animation: none;
  }
  .slide-enter-active,
  .slide-leave-active,
  .fade-enter-active,
  .fade-leave-active {
    transition: none;
  }
}
</style>
