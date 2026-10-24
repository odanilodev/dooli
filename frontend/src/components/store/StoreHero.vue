<template>
  <section class="store-hero-block" :class="{ 'is-compact': compact }">
    <HeroCarousel :banners="banners" />

    <div v-if="benefits.length && !compact" class="store-hero-benefits">
      <div v-for="(benefit, index) in benefits" :key="index" class="store-benefit">
        <span class="store-benefit-icon">
          <svg class="icon" width="19" height="19" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l2.5 2.5L16 9"/></svg>
        </span>
        <span>
          <strong>{{ benefit.title }}</strong>
          <small v-if="benefit.description">{{ benefit.description }}</small>
        </span>
      </div>
    </div>
  </section>
</template>

<script setup>
import HeroCarousel from '../HeroCarousel.vue';

defineProps({
  banners: { type: Array, default: () => [] },
  benefits: { type: Array, default: () => [] },
  compact: { type: Boolean, default: false },
});
</script>

<style scoped>
.store-hero-block{
  --store-gutter:clamp(20px,3.6vw,64px);
  position:relative;isolation:isolate;
}
.store-hero-block :deep(.hero){
  border-radius:0;box-shadow:none;background:#07101a;
}
.store-hero-block :deep(.hero-slide){
  min-height:clamp(470px,40vw,560px);
  padding:clamp(48px,5vw,76px) var(--store-gutter) clamp(116px,9vw,150px);
  gap:16px;justify-content:center;
}
.store-hero-block :deep(.hero-bg){
  transform:scale(1.01);
}
.store-hero-block :deep(.hero-mask){
  background:
    linear-gradient(90deg,rgba(7,16,26,.97) 0%,rgba(7,16,26,.88) 38%,rgba(7,16,26,.44) 72%,rgba(7,16,26,.16) 100%),
    linear-gradient(180deg,rgba(7,16,26,.05) 0%,rgba(7,16,26,.2) 62%,rgba(7,16,26,.72) 100%);
}
.store-hero-block :deep(.hero-eyebrow){
  color:color-mix(in srgb,var(--accent) 82%,#fff);
  font-size:10.5px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;
}
.store-hero-block :deep(.hero-title){
  max-width:780px;color:#fff;font-size:clamp(40px,4.8vw,66px);
  font-weight:800;line-height:1.04;letter-spacing:-.045em;text-wrap:balance;
}
.store-hero-block :deep(.hero-title em){
  display:block;color:var(--accent);
}
.store-hero-block :deep(.hero-desc){
  max-width:560px;color:rgba(255,255,255,.72);
  font-size:clamp(14px,1.1vw,16px);line-height:1.65;
}
.store-hero-block :deep(.hero-actions){
  gap:18px;margin-top:5px;
}
.store-hero-block :deep(.hero-actions .btn){
  padding:13px 20px;font-size:13px;border-radius:8px;
}
.store-hero-block :deep(.hero-quote){display:none;}
.store-hero-block :deep(.hero-dots){
  left:var(--store-gutter);bottom:104px;
}
.store-hero-block :deep(.hero-dots span){background:rgba(255,255,255,.36);}
.store-hero-block :deep(.hero-dots span.active){background:var(--accent);}
.store-hero-block :deep(.hero-nav){
  width:38px;height:38px;background:rgba(4,12,20,.48);
  border-color:rgba(255,255,255,.2);backdrop-filter:blur(6px);
}
.store-hero-block :deep(.hero-nav.prev){left:16px;}
.store-hero-block :deep(.hero-nav.next){right:16px;}
.store-hero-block :deep(.hero-nav:hover){background:var(--accent);color:var(--accent-ink);}

.store-hero-benefits{
  position:absolute;left:var(--store-gutter);right:var(--store-gutter);bottom:24px;z-index:2;
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));
  border-top:1px solid rgba(255,255,255,.18);
}
.store-benefit{display:flex;align-items:center;gap:11px;padding:18px 26px;}
.store-benefit:first-child{padding-left:0;}
.store-benefit + .store-benefit{border-left:1px solid rgba(255,255,255,.15);}
.store-benefit-icon{display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--accent);}
.store-benefit > span:last-child{display:flex;flex-direction:column;gap:2px;min-width:0;color:#fff;}
.store-benefit strong{font-size:11.5px;line-height:1.35;}
.store-benefit small{font-size:10.5px;line-height:1.4;color:rgba(255,255,255,.62);}

.store-hero-block.is-compact :deep(.hero){
  border-radius:14px;box-shadow:var(--shadow-sm);
}
.store-hero-block.is-compact :deep(.hero-slide){
  min-height:190px;padding:28px 34px;gap:8px;
}
.store-hero-block.is-compact :deep(.hero-title){
  max-width:620px;font-size:clamp(26px,3.2vw,38px);line-height:1.08;
}
.store-hero-block.is-compact :deep(.hero-title em){display:inline;margin-left:.18em;}
.store-hero-block.is-compact :deep(.hero-desc){
  max-width:520px;font-size:12px;line-height:1.55;
}
.store-hero-block.is-compact :deep(.hero-actions){gap:10px;margin-top:2px;}
.store-hero-block.is-compact :deep(.hero-actions .btn){
  padding:9px 14px;font-size:11.5px;
}
.store-hero-block.is-compact :deep(.hero-dots){left:34px;bottom:14px;}
.store-hero-block.is-compact :deep(.hero-quote){display:none;}
.store-hero-block.is-compact :deep(.hero-nav){display:none;}

@media(max-width:900px){
  .store-hero-block :deep(.hero-slide){
    min-height:430px;padding:42px var(--store-gutter) 46px;
  }
  .store-hero-block :deep(.hero-title){max-width:620px;}
  .store-hero-benefits{
    position:static;grid-template-columns:1fr;padding:4px var(--store-gutter);
    background:var(--bg-page);border-top:1px solid var(--border);
  }
  .store-benefit{padding:13px 0;}
  .store-benefit + .store-benefit{border-left:0;border-top:1px solid var(--border);}
  .store-benefit > span:last-child{color:var(--text-primary);}
  .store-benefit small{color:var(--text-muted);}
  .store-hero-block :deep(.hero-dots){bottom:20px;}
  .store-hero-block :deep(.hero-quote){display:none;}
}
@media(max-width:700px){
  .store-hero-block :deep(.hero-slide){min-height:400px;padding-top:38px;}
  .store-hero-block :deep(.hero-title){font-size:clamp(34px,9.2vw,46px);}
  .store-hero-block :deep(.hero-desc){max-width:100%;}
  .store-hero-block.is-compact :deep(.hero-slide){min-height:175px;padding:22px;}
  .store-hero-block.is-compact :deep(.hero-desc){display:none;}
  .store-hero-block.is-compact :deep(.hero-actions){margin-top:6px;}
}
@media(prefers-reduced-motion:reduce){
  .store-hero-block :deep(.hero-track){transition:none;}
}
</style>
