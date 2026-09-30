<template>
  <template v-if="node.type === 'section'">
    <span class="nav-group-label">{{ node.name }}</span>
    <NavItem v-for="child in node.children" :key="child.id" :node="child" />
  </template>

  <template v-else-if="node.type === 'dropdown'">
    <button
      type="button"
      class="nav-link nav-toggle"
      @click="toggle(node.id, node.depth)"
    >
      <NavIcon :name="node.icon" />
      <span>{{ node.name }}</span>
      <svg class="nav-caret" :class="{ open: isOpen(node.id, node.depth) }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <transition name="nav-drop">
      <div v-show="isOpen(node.id, node.depth)" class="nav-submenu">
        <NavItem v-for="child in node.children" :key="child.id" :node="child" />
      </div>
    </transition>
  </template>

  <template v-else>
    <router-link
      :to="node.link || '#'"
      class="nav-link"
      :class="{ active: isActive(node.link) }"
      @click="close"
    >
      <NavIcon :name="node.icon" />
      <span>{{ node.name }}</span>
    </router-link>
    <div v-if="node.children && node.children.length" class="nav-submenu">
      <NavItem v-for="child in node.children" :key="child.id" :node="child" />
    </div>
  </template>
</template>

<script setup>
import NavIcon from './NavIcon.vue';
import { useSidebar } from '../composables/useSidebar';
import { useNavMenu } from '../composables/useNavMenu';

defineOptions({ name: 'NavItem' });

defineProps({
  node: { type: Object, required: true },
});

const { close } = useSidebar();
const { isOpen, toggle, isActive } = useNavMenu();
</script>

<style scoped>
.nav-group-label{
  font-size:11px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;
  color:var(--text-muted);margin:14px 0 2px;padding-left:12px;
}
.nav-toggle{width:100%;}
.nav-caret{
  margin-left:auto;flex:none;opacity:.7;transition:transform .2s ease;
}
.nav-caret.open{transform:rotate(180deg);}
.nav-submenu{
  display:flex;flex-direction:column;gap:2px;
  margin:2px 0 6px 18px;padding-left:12px;border-left:1px solid var(--border);
}
.nav-submenu :deep(.nav-link){font-size:13px;padding:8px 12px;}
.nav-drop-enter-active,.nav-drop-leave-active{transition:opacity .18s ease,transform .18s ease;}
.nav-drop-enter-from,.nav-drop-leave-to{opacity:0;transform:translateY(-4px);}
</style>
