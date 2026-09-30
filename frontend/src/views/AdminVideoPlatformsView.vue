<template>
  <main class="content">

    <div class="page-header">
      <div>
        <h1 class="page-title">Plataformas de vídeo</h1>
        <p class="page-subtitle">Configure as credenciais dos provedores de vídeo da empresa.</p>
      </div>
    </div>

    <p v-if="loading" class="page-subtitle">Carregando...</p>

    <div v-else class="vp-grid">
      <form
        v-for="p in platforms"
        :key="p.id"
        class="panel"
        @submit.prevent="save(p)"
      >
        <div class="vp-head">
          <div>
            <h2 class="panel-title">{{ p.name }}</h2>
            <p class="panel-desc">código: {{ p.code }}</p>
          </div>
          <span class="vp-badge" :class="p.status === 'active' ? 'on' : 'off'">{{ p.status }}</span>
        </div>

        <div class="form-group">
          <label :for="`vp-secret-${p.id}`">
            Credencial ({{ credentialKey(p) }})
            <span class="vp-hint">{{ p.has_credentials ? 'já configurada — deixe vazio para manter' : 'não configurada' }}</span>
          </label>
          <input
            class="form-control"
            type="password"
            :id="`vp-secret-${p.id}`"
            v-model="forms[p.id].api_secret"
            autocomplete="off"
            placeholder="••••••••"
          >
        </div>

        <div class="form-row">
          <div class="form-group">
            <label :for="`vp-ttl-${p.id}`">TTL do OTP (segundos)</label>
            <input class="form-control" type="number" min="0" :id="`vp-ttl-${p.id}`" v-model="forms[p.id].ttl" placeholder="3600">
          </div>
          <div class="form-group">
            <label :for="`vp-status-${p.id}`">Status</label>
            <select class="form-control" :id="`vp-status-${p.id}`" v-model="forms[p.id].status">
              <option value="active">active</option>
              <option value="inactive">inactive</option>
            </select>
          </div>
        </div>

        <span class="form-error" :class="{ show: rowErrors[p.id] }">{{ rowErrors[p.id] }}</span>

        <div>
          <button type="submit" class="btn btn-primary" :disabled="savingId === p.id">
            {{ savingId === p.id ? 'Salvando...' : 'Salvar' }}
          </button>
        </div>
      </form>
    </div>

  </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { api } from '../api/http';
import { useToast } from '../composables/useToast';

const toast = useToast();

const platforms = ref([]);
const forms = reactive({});
const rowErrors = reactive({});
const loading = ref(true);
const savingId = ref(null);

function credentialKey(platform) {
  return platform.code === 'panda' ? 'api_key' : 'api_secret';
}

async function load() {
  loading.value = true;

  try {
    const { data } = await api.get('/admin/video-platforms', { params: { per_page: 50 } });
    platforms.value = data.data;

    for (const p of data.data) {
      forms[p.id] = {
        api_secret: '',
        ttl: p.settings?.ttl ?? '',
        status: p.status,
      };
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível carregar as plataformas.');
  } finally {
    loading.value = false;
  }
}

async function save(platform) {
  const form = forms[platform.id];
  const payload = { status: form.status };

  if (form.api_secret?.trim()) {
    payload.credentials = { [credentialKey(platform)]: form.api_secret.trim() };
  }

  if (form.ttl !== '' && form.ttl !== null && form.ttl !== undefined) {
    payload.settings = { ...(platform.settings || {}), ttl: Number(form.ttl) };
  }

  savingId.value = platform.id;
  rowErrors[platform.id] = '';

  try {
    const { data } = await api.put(`/admin/video-platforms/${platform.id}`, payload);
    const updated = data.data;

    platform.has_credentials = updated.has_credentials;
    platform.status = updated.status;
    platform.settings = updated.settings;
    forms[platform.id].api_secret = '';

    toast.success('Configuração salva.');
  } catch (e) {
    rowErrors[platform.id] = e?.message || 'Não foi possível salvar.';
  } finally {
    savingId.value = null;
  }
}

onMounted(load);
</script>

<style scoped>
.vp-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 18px;
}
.vp-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}
.vp-badge {
  font-size: 12px;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.vp-badge.on {
  background: rgba(34, 197, 94, .15);
  color: #22c55e;
}
.vp-badge.off {
  background: rgba(148, 163, 184, .18);
  color: #94a3b8;
}
.vp-hint {
  display: block;
  font-weight: 400;
  font-size: 12px;
  opacity: .7;
}
</style>
