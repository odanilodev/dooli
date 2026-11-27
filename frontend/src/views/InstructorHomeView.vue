<template>
  <main class="content">

    <!-- Com um único menu liberado a tela não aparece: o instrutor é levado direto a ele. -->
    <template v-if="ready && !onlyLink">
      <div class="page-header">
        <div>
          <h1 class="page-title">Olá, {{ firstName }}</h1>
          <p class="page-subtitle">Este é o seu painel de instrutor.</p>
        </div>
      </div>

      <section v-if="shortcuts.length" class="section">
        <h2 class="section-title">Atalhos</h2>
        <div class="shortcut-grid">
          <router-link v-for="item in shortcuts" :key="item.id" :to="item.link" class="shortcut">
            <NavIcon :name="item.icon" />
            <span>{{ item.name }}</span>
          </router-link>
        </div>
      </section>

      <div v-else-if="!auth.canAccessAdmin || menus.loaded" class="empty-state">
        <strong>Nenhum atalho disponível</strong>
        <span>Peça ao administrador para liberar os menus do seu perfil.</span>
      </div>
    </template>

  </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import NavIcon from '../components/NavIcon.vue';
import { useAuthStore } from '../stores/auth';
import { useMenusStore } from '../stores/menus';
import { ADMIN_ONLY_LINKS } from '../utils/adminLinks';

const router = useRouter();
const auth = useAuthStore();
const menus = useMenusStore();

const mounted = ref(false);

const firstName = computed(() => auth.user?.name?.trim().split(/\s+/)[0] ?? '');

function flatten(nodes) {
  return nodes.flatMap((node) => [node, ...flatten(node.children ?? [])]);
}

const shortcuts = computed(() => {
  if (!auth.canAccessAdmin) {
    return [];
  }

  return flatten(menus.tree).filter(
    (node) => node.link?.startsWith('/admin/') && !ADMIN_ONLY_LINKS.includes(node.link),
  );
});

// Evita piscar a saudação enquanto os menus ainda estão carregando.
const ready = computed(() => !auth.canAccessAdmin || menus.loaded || (mounted.value && !menus.loading));

const onlyLink = computed(() => (shortcuts.value.length === 1 ? shortcuts.value[0].link : null));

watch(
  onlyLink,
  (link) => {
    if (link) {
      router.replace(link);
    }
  },
  { immediate: true },
);

onMounted(() => {
  if (auth.canAccessAdmin && !menus.loaded && !menus.loading) {
    menus.fetchMyMenus();
  }

  mounted.value = true;
});
</script>

<style scoped>
.shortcut-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;}
.shortcut{
  display:flex;align-items:center;gap:10px;padding:16px;background:var(--bg-card);border:1px solid var(--border);
  border-radius:12px;color:var(--text-primary);font-size:13px;font-weight:700;transition:border-color .14s ease;
}
.shortcut:hover{border-color:var(--accent);}
</style>
