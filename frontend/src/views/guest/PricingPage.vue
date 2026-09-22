<template>
  <main class="pricing-page">
    <header class="pricing-nav">
      <router-link to="/" class="brand" aria-label="BloomCraft home">
        <img src="../../../public/bloomcraft-blankBg.png" alt="" width="42" height="42" />
        <span>BloomCraft</span>
      </router-link>
      <nav aria-label="Main navigation">
        <router-link to="/shop">Shop</router-link>
        <router-link to="/pricing" class="is-current">Pricing</router-link>
      </nav>
      <div class="nav-actions">
        <router-link to="/guest/login" class="button button--quiet">Login</router-link>
        <router-link to="/guest/register" class="button">Register</router-link>
      </div>
    </header>

    <section class="pricing-hero">
      <p class="eyebrow">BloomCraft for growing flower businesses</p>
      <h1>Choose the tools that help your business <span>bloom.</span></h1>
      <p>Start with the essentials, then grow into a connected ERP workspace when your operations need more.</p>
    </section>

    <section class="plans" aria-label="Subscription plans">
      <PricingPlanCard v-for="plan in plans" :key="plan.name" :plan="plan" />
    </section>

    <section class="feature-overview" aria-labelledby="included-features-heading">
      <div class="section-heading">
        <p class="eyebrow">What is included</p>
        <h2 id="included-features-heading">Features organized around how your team works</h2>
        <p>Professional includes the complete, normal BloomCraft ERP. Enterprise keeps those same ERP tools and adds the scale and support larger operations need.</p>
      </div>

      <div class="feature-groups">
        <article v-for="group in featureGroups" :key="group.name" class="feature-group">
          <div class="group-heading">
            <h3>{{ group.name }}</h3>
            <span>{{ group.availableFrom }}{{ group.availableFrom !== 'Starter' ? '+' : '' }}</span>
          </div>
          <ul>
            <li v-for="feature in group.features" :key="feature">{{ feature }}</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="enterprise-note">
      <div>
        <p class="eyebrow">Built for scale</p>
        <h2>Enterprise expands your operation, not your core ERP.</h2>
      </div>
      <ul>
        <li>Multi-branch and multi-warehouse operations</li>
        <li>Higher or custom operational limits</li>
        <li>Custom integrations and workflows</li>
        <li>Dedicated support for enterprise-scale teams</li>
      </ul>
    </section>

    <section class="pricing-cta">
      <p class="eyebrow">Ready when you are</p>
      <h2>Set up your BloomCraft business today.</h2>
      <router-link to="/guest/vendor_register" class="button button--large">Become a vendor</router-link>
    </section>

    <footer>© {{ year }} BloomCraft. All rights reserved.</footer>
  </main>
</template>

<script setup>
import PricingPlanCard from "../../components/pricing/PricingPlanCard.vue";

const year = new Date().getFullYear();

const plans = [
  { name: "STARTER", price: "₱999", priceSuffix: "/month", description: "Essential ecommerce and day-to-day business tools for getting started.", highlights: ["Ecommerce functionality", "Products, reservations, calendar, and chat", "Basic business management"], action: "Get started" },
  { name: "BUSINESS", price: "₱2,999", priceSuffix: "/month", description: "Everything in Starter with the foundational ERP tools for a connected business.", highlights: ["Everything in Starter", "Basic ERP functionality", "Finance, procurement, and supplier workflows"], action: "Choose Business" },
  { name: "PROFESSIONAL", price: "₱6,999", priceSuffix: "/month", description: "The complete normal BloomCraft ERP for teams running end-to-end operations.", highlights: ["Everything in Business", "Full ERP functionality", "Warehouse, supply chain, CRM, and HR"], action: "Choose Professional", featured: true },
  { name: "ENTERPRISE", price: "₱15,000+", priceSuffix: "/month", description: "Professional ERP capabilities with enterprise-scale flexibility and support.", highlights: ["Everything in Professional", "Multi-branch and multi-warehouse", "Custom integrations and dedicated support"], action: "Talk to us" },
];

const featureGroups = [
  { name: "Ecommerce", availableFrom: "Starter", features: ["Products", "Reservations / Orders", "Calendar", "Add Product", "Chat"] },
  { name: "Finance", availableFrom: "Business", features: ["Finance Dashboard", "Funding Requests", "Payroll Requests"] },
  { name: "Procurement", availableFrom: "Business", features: ["Funding Requests", "Products", "Add Product"] },
  { name: "Suppliers", availableFrom: "Business", features: ["Supplier List", "Create Supplier", "Edit Supplier"] },
  { name: "Warehouse", availableFrom: "Professional", features: ["Warehouse", "Inventory", "Add Item", "Floor View", "Batches to Receive", "Locations", "Scanner"] },
  { name: "Supply Chain", availableFrom: "Professional", features: ["Supply Chain Dashboard"] },
  { name: "Orders", availableFrom: "Professional", features: ["Orders", "Create Order", "Order Details"] },
  { name: "Logistics", availableFrom: "Professional", features: ["Shipment List", "Shipment Tracking"] },
  { name: "Deliveries", availableFrom: "Professional", features: ["Delivery List", "Vendor Orders"] },
  { name: "Scanning", availableFrom: "Professional", features: ["Process", "Ship", "Receive", "Completed"] },
  { name: "CRM", availableFrom: "Professional", features: ["Chat"] },
  { name: "HR", availableFrom: "Professional", features: ["HR Dashboard"] },
  { name: "Employees", availableFrom: "Professional", features: ["Employee Directory", "Employee Profiles"] },
  { name: "Attendance", availableFrom: "Professional", features: ["Attendance Logs", "QR Scanner"] },
  { name: "Payroll", availableFrom: "Professional", features: ["Payroll List", "Create Payroll"] },
  { name: "Leave", availableFrom: "Professional", features: ["Employee Requests", "Management Requests", "QR Requests"] },
];
</script>

<style scoped>
:global(body) { margin: 0; background: #faf8f5; }
.pricing-page { --ink: #1e1b18; --ink-soft: #6c655f; --ivory: #faf8f5; --ivory-deep: #f1ebe3; --rosewood: #b86558; --gold: #d4b27a; --line: rgba(30, 27, 24, 0.12); min-height: 100vh; overflow: hidden; color: var(--ink); background: radial-gradient(circle at 7% 8%, rgba(242, 206, 194, .58), transparent 25rem), radial-gradient(circle at 96% 37%, rgba(212, 178, 122, .18), transparent 23rem), var(--ivory); font-family: "Poppins", sans-serif; }
.pricing-nav { display: flex; align-items: center; justify-content: space-between; gap: 30px; max-width: 1200px; margin: 0 auto; padding: 22px 6%; }
.brand { display: inline-flex; align-items: center; gap: 9px; color: var(--ink); font-family: "Montserrat", "Poppins", sans-serif; font-size: 21px; font-weight: 700; text-decoration: none; letter-spacing: -.02em; }
.brand img { object-fit: contain; }
.pricing-nav nav { display: flex; gap: 28px; }
.pricing-nav nav a { position: relative; color: var(--ink); font-size: 14px; text-decoration: none; }
.pricing-nav nav .is-current { color: var(--rosewood); font-weight: 600; }
.pricing-nav nav .is-current::after { position: absolute; right: 0; bottom: -6px; left: 0; height: 2px; background: var(--rosewood); content: ""; }
.nav-actions { display: flex; gap: 10px; }
.button { display: inline-block; padding: 10px 22px; color: var(--ivory); background: var(--ink); border: 1px solid var(--ink); border-radius: 999px; font-size: 13.5px; font-weight: 500; text-decoration: none; transition: transform .25s ease, background .25s ease; }
.button:hover { background: var(--rosewood); border-color: var(--rosewood); transform: translateY(-2px); }
.button--quiet { color: var(--ink); background: transparent; border-color: var(--line); }
.button--quiet:hover { color: var(--ivory); }
.pricing-hero { max-width: 760px; margin: 70px auto 52px; padding: 0 24px; text-align: center; }
.eyebrow { margin: 0 0 14px; color: var(--rosewood); font-size: 11.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
h1, h2, h3, p { margin-top: 0; }
.pricing-hero h1 { max-width: 700px; margin: 0 auto 18px; font-size: clamp(38px, 5vw, 62px); line-height: 1.08; letter-spacing: -.05em; }
.pricing-hero h1 span { color: var(--rosewood); }
.pricing-hero > p:last-child, .section-heading > p:last-child { color: var(--ink-soft); font-size: 16px; line-height: 1.8; }
.plans { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; max-width: 1200px; margin: 0 auto; padding: 0 6%; }
.feature-overview { max-width: 1200px; margin: 120px auto 0; padding: 86px 6%; border-top: 1px solid var(--line); }
.section-heading { max-width: 720px; margin-bottom: 42px; }
.section-heading h2, .enterprise-note h2, .pricing-cta h2 { margin-bottom: 16px; font-size: clamp(29px, 3.5vw, 44px); line-height: 1.16; letter-spacing: -.04em; }
.feature-groups { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.feature-group { padding: 23px 20px; background: rgba(255,255,255,.5); border: 1px solid var(--line); border-radius: 16px; }
.group-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; margin-bottom: 17px; }
.group-heading h3 { margin: 0; font-size: 16px; }
.group-heading span { color: var(--rosewood); font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; }
.feature-group ul { display: grid; gap: 7px; margin: 0; padding: 0; list-style: none; color: var(--ink-soft); font-size: 12.5px; line-height: 1.45; }
.feature-group li::before { margin-right: 7px; color: var(--gold); content: "•"; }
.enterprise-note { display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center; max-width: 1080px; margin: 0 auto; padding: 70px 6%; color: var(--ivory); background: var(--ink); border-radius: 28px; }
.enterprise-note .eyebrow { color: var(--gold); }
.enterprise-note h2 { margin: 0; }
.enterprise-note ul { display: grid; gap: 13px; margin: 0; padding: 0; list-style: none; color: rgba(250,248,245,.78); font-size: 14px; line-height: 1.55; }
.enterprise-note li { display: flex; gap: 10px; }
.enterprise-note li::before { color: var(--gold); content: "✓"; }
.pricing-cta { padding: 120px 24px; text-align: center; }
.pricing-cta h2 { max-width: 560px; margin-right: auto; margin-left: auto; }
.button--large { padding: 15px 32px; }
footer { padding: 28px 24px; color: rgba(250,248,245,.55); background: #2b2723; font-size: 12.5px; text-align: center; }
@media (max-width: 1050px) { .plans { grid-template-columns: repeat(2, minmax(0, 1fr)); max-width: 760px; } .feature-groups { grid-template-columns: repeat(3, minmax(0, 1fr)); } .enterprise-note { margin-right: 5%; margin-left: 5%; } }
@media (max-width: 700px) { .pricing-nav { padding: 16px 5%; } .pricing-nav nav { display: none; } .brand { font-size: 18px; } .brand img { width: 35px; height: 35px; } .nav-actions .button { padding: 8px 14px; font-size: 12px; } .pricing-hero { margin-top: 54px; } .plans { grid-template-columns: 1fr; padding: 0 5%; } .feature-overview { margin-top: 80px; padding: 68px 5%; } .feature-groups { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; } .feature-group { padding: 18px 15px; } .group-heading { display: block; } .group-heading h3 { margin-bottom: 5px; } .enterprise-note { grid-template-columns: 1fr; gap: 28px; margin: 0 5%; padding: 44px 28px; border-radius: 20px; } .pricing-cta { padding: 85px 24px; } }
@media (max-width: 420px) { .pricing-nav { gap: 10px; } .nav-actions { gap: 5px; } .nav-actions .button { padding: 7px 10px; } .feature-groups { grid-template-columns: 1fr; } }
</style>
