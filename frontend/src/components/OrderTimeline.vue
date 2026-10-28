<template>
  <ol class="order-timeline" :class="{ compact }">
    <li v-for="(event, index) in events" :key="event.id ?? `${event.type}-${index}`" :class="tone(event)">
      <span class="timeline-dot" aria-hidden="true"></span>
      <div class="timeline-copy">
        <div class="timeline-head">
          <strong>{{ event.title }}</strong>
          <time v-if="event.occurred_at" :datetime="event.occurred_at">{{ formatDateTime(event.occurred_at) }}</time>
        </div>
        <p v-if="event.description">{{ event.description }}</p>
        <div v-if="admin && (event.actor_name || event.estimated)" class="timeline-meta">
          <span v-if="event.actor_name">Por {{ event.actor_name }}</span>
          <span v-if="event.estimated">Reconstruído do histórico</span>
        </div>
      </div>
    </li>
  </ol>
</template>

<script setup>
import { formatDateTime } from '../utils/format';
import { orderEventTone } from '../utils/orderStatus';

defineProps({
  events: { type: Array, default: () => [] },
  admin: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});

function tone(event) {
  return orderEventTone(event.type);
}
</script>

<style scoped>
.order-timeline{display:flex;flex-direction:column;gap:0;margin:0;padding:0;list-style:none;}
.order-timeline li{position:relative;display:flex;gap:14px;padding-bottom:24px;}
.order-timeline li:last-child{padding-bottom:0;}
.order-timeline li::before{content:'';position:absolute;left:7px;top:17px;bottom:0;width:1px;background:var(--border);}
.order-timeline li:last-child::before{display:none;}
.timeline-dot{position:relative;z-index:1;width:15px;height:15px;border-radius:50%;flex-shrink:0;margin-top:2px;border:2px solid var(--border);background:var(--bg-card);}
.order-timeline li.success .timeline-dot{border-color:var(--accent);background:var(--accent);}
.order-timeline li.warning .timeline-dot{border-color:#d9a514;background:#d9a514;}
.order-timeline li.danger .timeline-dot{border-color:#ff6b6b;background:#ff6b6b;}
.timeline-copy{display:flex;flex-direction:column;gap:4px;min-width:0;}
.timeline-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.timeline-head strong{font-size:13.5px;line-height:1.35;}
.timeline-head time{color:var(--text-muted);font-size:11.5px;white-space:nowrap;padding-top:2px;}
.timeline-copy p{margin:0;color:var(--text-muted);font-size:12px;line-height:1.55;}
.timeline-meta{display:flex;flex-wrap:wrap;gap:8px;color:var(--text-muted);font-size:11px;}
.timeline-meta span{padding:2px 7px;border-radius:99px;background:var(--bg-card-alt);}
.order-timeline.compact li{padding-bottom:18px;}
</style>
