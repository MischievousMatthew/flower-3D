---
target: the Shop page
total_score: 28
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:C:\\Users\\Laptop\\Documents\\flower-3D\\frontend\\src\\views\\authenticated\\Shop.vue"
target_fingerprint: "sha256:b8df05800cdaca69ea612e546b34f6b344de23472d1524d25504c0af1a0983fc"
target_path: "C:\\Users\\Laptop\\Documents\\flower-3D\\frontend\\src\\views\\authenticated\\Shop.vue"
timestamp: 2026-11-04T15-48-38Z
slug: frontend-src-views-authenticated-shop-vue
---
⚠️ DEGRADED: single-context (sub-agents not spawned; assessments A and B run in one context)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 3 | Counts, chips, skeletons, end-of-list message |
| 2 | Match System / Real World | 3 | Peso prices, flowers counted as flowers, real map |
| 3 | User Control and Freedom | 3 | Search, Sort, removable chips, Clear all |
| 4 | Consistency and Standards | 3 | Unified product badges; "Verified" pill differs |
| 5 | Error Prevention | 3 | Sold-out can't be added; radius bounded 1-5 km |
| 6 | Recognition Rather Than Recall | 3 | Distinct occasion icons |
| 7 | Flexibility and Efficiency | 2 | Search/sort not kept in URL or across reloads |
| 8 | Aesthetic and Minimalist Design | 3 | Repeats cut; secondary grey slightly under contrast |
| 9 | Error Recovery | 3 | No-results copy names the recovery; map retry |
| 10 | Help and Documentation | 2 | Near-me filter only explained under the map |
| **Total** | | **28/40** | **Good** |

## Design Specificity Verdict

Reads as BloomCraft's own: hero photography, distinct occasion icons, logo-pin map beside the shop list, the 3D Designer. Familiar storefront structure suits an Operate surface.

Deterministic scan: 1 source finding (border-accent-on-rounded, ShopMap.vue:835) is a false positive (CSS triangle of the map pin tail). Live overlay: 22 findings: low-contrast --ink-soft #6a7282 on #f2f5f2 at 4.4:1 (6), "Verified" badge 4.4:1 and 10.5px (10), tiny text 11.5px (7, incl. addresses), 10.5px badge (1), italic serif hero h1 (brand-committed, kept), hero eyebrow (1), straggler in finder copy (1).

## Priority Issues

- [P1] Secondary grey (--ink-soft) at 4.4:1 on page background, 6 places. Fix: darken token to ~#5f6776. /impeccable polish
- [P1] "Verified" shop badge: 10.5px, 4.4:1. Fix: 12px/600, white on deep green with a check icon. /impeccable polish
- [P2] Functional text under 12px (badges 10.5px, addresses 11.5px). Fix: 12px floor. /impeccable typeset
- [P2] Hero eyebrow "Fresh flowers for every moment" (banned pattern). Fix: remove. /impeccable distill
- [P3] Straggler word in finder description. Fix: text-wrap: pretty. /impeccable typeset

## Persona Red Flags

- First-timer: trust badge is the faintest text on the shop card.
- Low-vision shopper: helper lines at 4.4:1 get skipped outdoors.
- Marie (gift buyer, Cavite): search/sort lost on reload.

## Minor Observations

- Sort/search not in URL; "From ₱0" on shops without a minimum; phone width unchecked.

## Questions to Consider

- Should "Verified" be the most visible thing on the shop card?
- Should the page remember the last search and sort?
