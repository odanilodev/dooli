<template>
  <div class="drawer-backdrop" @mousedown="onBackdropDown" @click.self="backdropDown && emit('close')">
    <aside class="drawer">
      <header class="drawer-head">
        <div class="drawer-head-text">
          <span class="drawer-eyebrow">{{ typeLabel(type) }}</span>
          <h2 class="drawer-title">{{ item.name }}</h2>
        </div>
        <span class="status-pill" :class="item.status">{{ statusLabel(item.status) }}</span>
        <button class="drawer-close" @click="emit('close')" aria-label="Fechar">×</button>
      </header>

      <nav class="drawer-tabs">
        <button
          v-for="t in tabs"
          :key="t"
          class="drawer-tab"
          :class="{ active: tab === t }"
          @click="tab = t"
        >{{ tabLabels[t] }}</button>
      </nav>

      <div class="drawer-body">
        <!-- DETALHES -->
        <template v-if="tab === 'details'">
          <div class="form-group">
            <label :for="`dw-name`">Nome</label>
            <input :id="`dw-name`" class="form-control" v-model="form.name" @keydown.enter.prevent="save">
          </div>

          <template v-if="type !== 'topic'">
            <div class="form-group">
              <label :for="`dw-desc`">Descrição</label>
              <textarea :id="`dw-desc`" class="form-control" rows="3" v-model="form.description"></textarea>
            </div>
          </template>

          <template v-if="type === 'course' || type === 'module'">
            <div class="form-group">
              <label :for="`dw-image`">Imagem</label>
              <div v-if="imagePreviewUrl" class="dw-image-preview">
                <img :src="imagePreviewUrl" alt="Pré-visualização da imagem">
                <button type="button" class="dw-image-remove" @click="clearImage">remover</button>
              </div>
              <FilePicker
                accept="image/png,image/jpeg,image/webp"
                title="Enviar imagem"
                hint="JPG, PNG ou WEBP até 4MB"
                :file-name="imageFile?.name || ''"
                @change="onImagePicked"
              />
              <input :id="`dw-image`" class="form-control" v-model="form.image" placeholder="ou cole uma URL https://...">
            </div>
          </template>

          <template v-if="type === 'lesson'">
            <div class="form-group">
              <label :for="`dw-required`">Pré-requisito (aula obrigatória)</label>
              <SearchSelect
                :id="`dw-required`"
                v-model="form.required_lesson_id"
                :options="prerequisiteSelectOptions"
                placeholder="Buscar aula..."
                allow-clear
                clear-label="Nenhuma"
              />
              <p class="form-hint">O aluno só libera esta aula após concluir a aula escolhida. Só aparecem aulas anteriores na ordem do módulo ou curso.</p>
            </div>

            <div v-if="form.content_type === 'video'" class="form-group">
              <label class="dw-option" :class="{ 'is-on': form.require_full_watch }">
                <input type="checkbox" v-model="form.require_full_watch">
                <span class="dw-option-text">
                  <span class="dw-option-title">Exigir assistir até o final</span>
                  <span class="dw-option-desc">O aluno só consegue marcar a aula como concluída depois de assistir o vídeo até o fim. Requer vídeo vinculado.</span>
                </span>
              </label>
            </div>

            <p v-if="lessonLocation" class="form-hint">Local: {{ lessonLocation }}</p>
          </template>

          <p v-if="type === 'topic'" class="form-hint">Módulo: {{ course?.modules?.find((m) => m.id === item.module_id)?.name || `#${item.module_id}` }}</p>
        </template>

        <!-- CURSOS -->
        <template v-else-if="tab === 'courses'">
          <div class="form-group">
            <label>Cursos vinculados ({{ linkedCourses.length }})</label>
            <div v-if="linkedCourses.length" class="dw-list">
              <div v-for="c in linkedCourses" :key="c.id" class="dw-row">
                <span class="dw-info">
                  <span class="dw-row-name">{{ c.name }}</span>
                  <span class="dw-row-sub">#{{ c.id }}</span>
                </span>
                <button type="button" class="btn btn-danger btn-sm" :disabled="busy" @click="unlinkCourse(c)">Desassociar</button>
              </div>
            </div>
            <p v-else class="form-hint">Nenhum curso vinculado.</p>
          </div>

          <div class="drawer-divider"></div>

          <div class="form-group">
            <label for="dw-course-search">Vincular a um curso</label>
            <input id="dw-course-search" class="form-control" v-model="courseSearch" @input="searchCourses" placeholder="Buscar curso...">
          </div>

          <p v-if="searchingCourses" class="form-hint">Buscando...</p>
          <div v-else-if="courseResults.length" class="dw-list">
            <div v-for="c in courseResults" :key="c.id" class="dw-row">
              <span class="dw-info">
                <span class="dw-row-name">{{ c.name }}</span>
                <span class="dw-row-sub">#{{ c.id }}</span>
              </span>
              <button type="button" class="btn btn-outline btn-sm" :disabled="busy" @click="linkCourse(c)">Vincular</button>
            </div>
          </div>
          <p v-else-if="courseSearch" class="form-hint">Nenhum curso encontrado.</p>
        </template>

        <!-- CONTEÚDO -->
        <template v-else-if="tab === 'content'">
          <div class="form-group">
            <label>Tipo de conteúdo</label>
            <div class="dw-seg">
              <button type="button" class="dw-seg-btn" :class="{ active: form.content_type === 'video' }" @click="setContentType('video')">Vídeo</button>
              <button type="button" class="dw-seg-btn" :class="{ active: form.content_type === 'document' }" @click="setContentType('document')">Documento</button>
              <button type="button" class="dw-seg-btn" :class="{ active: form.content_type === 'live' }" @click="setContentType('live')">Ao vivo</button>
              <button type="button" class="dw-seg-btn" :class="{ active: !form.content_type }" @click="setContentType('')">Sem mídia</button>
            </div>
          </div>

          <!-- VÍDEO -->
          <template v-if="form.content_type === 'video'">
            <div class="form-group">
              <label :for="`dw-video`">Vídeo da aula</label>
              <SearchSelect
                :id="`dw-video`"
                v-model="form.video_id"
                :options="videoOptions"
                :loading="videosLoading"
                remote
                placeholder="Buscar vídeo..."
                @search="searchVideos"
              />
            </div>

            <button type="button" class="drawer-link" @click="showVideo = !showVideo">
              {{ showVideo ? '− Cancelar' : '+ Criar novo vídeo' }}
            </button>

            <div v-if="showVideo" class="drawer-inline">
              <input class="form-control" v-model="newVideo.title" placeholder="Título do vídeo">
              <select class="form-control" v-model="newVideo.player">
                <option value="vdocipher">VdoCipher</option>
                <option value="panda">Panda Video</option>
                <option value="vimeo">Vimeo</option>
                <option value="fastpix">FastPix</option>
                <option value="external">Link externo</option>
              </select>

              <VideoUploader
                v-if="isInlineVdoCipher"
                ref="inlineUploader"
                :title="newVideo.title"
                @state="inlineUploadState = $event"
                @uploaded="onVideoUploaded"
              />

              <template v-else>
                <input class="form-control" v-model="newVideo.code" placeholder="Código no provedor">
                <input class="form-control" v-model="newVideo.link" placeholder="Link (para player externo)">
              </template>

              <button v-if="isInlineVdoCipher" type="button" class="btn btn-primary btn-sm" :disabled="!inlineUploadState.canSubmit" @click="inlineUploader?.submit()">
                {{ inlineUploadState.busy ? 'Enviando...' : 'Enviar e vincular' }}
              </button>
              <button v-else type="button" class="btn btn-primary btn-sm" :disabled="creatingVideo" @click="createVideo">
                {{ creatingVideo ? 'Criando...' : 'Criar e vincular' }}
              </button>
            </div>
          </template>

          <!-- DOCUMENTO -->
          <template v-else-if="form.content_type === 'document'">
            <div class="form-group">
              <label :for="`dw-doc`">Documento da aula</label>
              <SearchSelect
                :id="`dw-doc`"
                v-model="form.document_id"
                :options="documentOptions"
                :loading="documentsLoading"
                remote
                placeholder="Buscar documento..."
                @search="searchDocuments"
              />
            </div>

            <button type="button" class="drawer-link" @click="showDocument = !showDocument">
              {{ showDocument ? '− Cancelar' : '+ Enviar novo documento' }}
            </button>

            <div v-if="showDocument" class="drawer-inline">
              <DocumentUploader
                ref="documentUploader"
                :status="'active'"
                @state="documentUploadState = $event"
                @uploaded="onDocumentUploaded"
              />
              <button type="button" class="btn btn-primary btn-sm" :disabled="!documentUploadState.canSubmit" @click="documentUploader?.submit()">
                {{ documentUploadState.busy ? 'Enviando...' : 'Enviar e vincular' }}
              </button>
            </div>

            <p class="form-hint">PDFs são exibidos no player da aula; outros formatos ficam disponíveis para abrir/baixar.</p>
          </template>

          <!-- AO VIVO -->
          <template v-else-if="form.content_type === 'live'">
            <div class="form-group">
              <label :for="`dw-webinar`">Link do embed</label>
              <input :id="`dw-webinar`" class="form-control" v-model="form.webinar_link" placeholder="https://player.vdocipher.com/live-v2?liveId=...">
            </div>
            <div class="form-group">
              <label :for="`dw-webinar-at`">Início agendado</label>
              <input :id="`dw-webinar-at`" class="form-control" type="datetime-local" v-model="form.webinar_starts_at">
            </div>
            <p class="form-hint">
              Preencha o link para exibir a transmissão. Com data futura o aluno vê "Em breve"; a partir da data, o player ao vivo é liberado.
            </p>
          </template>

          <template v-else>
            <div class="form-group">
              <label :for="`dw-content`">Conteúdo (HTML)</label>
              <textarea :id="`dw-content`" class="form-control" rows="8" v-model="form.content" placeholder="<p>Conteúdo da aula...</p>"></textarea>
              <p class="form-hint">Aula sem vídeo, documento ou transmissão. Este conteúdo aparece na aba Descrição para o aluno.</p>
            </div>
          </template>
        </template>

        <!-- MATERIAIS -->
        <template v-else-if="tab === 'materials'">
          <div class="form-group">
            <label>Anexos</label>
            <div v-if="attachments.length" class="drawer-checks">
              <label v-for="a in attachments" :key="a.id" class="form-check">
                <input type="checkbox" :value="a.id" v-model="selectedAttachments">
                <span>{{ a.path }}</span>
              </label>
            </div>
            <p v-else class="form-hint">Nenhum anexo cadastrado.</p>
          </div>

          <button type="button" class="drawer-link" @click="showAttachment = !showAttachment">
            {{ showAttachment ? '− Cancelar' : '+ Criar novo anexo' }}
          </button>

          <div v-if="showAttachment" class="drawer-inline">
            <input class="form-control" v-model="newAttachment.path" placeholder="Caminho (path)">
            <button type="button" class="btn btn-primary btn-sm" :disabled="creatingAttachment" @click="createAttachment">
              {{ creatingAttachment ? 'Criando...' : 'Criar e vincular' }}
            </button>
          </div>
        </template>

        <!-- PUBLICAÇÃO -->
        <template v-else-if="tab === 'publish'">
          <div class="form-group">
            <label :for="`dw-status`">Status</label>
            <select :id="`dw-status`" class="form-control" v-model="form.status">
              <option value="active">Publicado</option>
              <option value="inactive">Rascunho</option>
            </select>
          </div>
        </template>
      </div>

      <footer class="drawer-foot">
        <button v-if="canPublish" type="button" class="btn btn-outline" :disabled="busy" @click="togglePublish">
          {{ published ? 'Despublicar' : 'Publicar' }}
        </button>
        <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Salvando...' : 'Salvar' }}
        </button>
      </footer>
    </aside>

    <ImageCropper
      v-if="cropFile"
      :file="cropFile"
      @confirm="onCropConfirm"
      @cancel="onCropCancel"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { api } from '../../api/http';
import { useToast } from '../../composables/useToast';
import { playerLabel, statusLabel, typeLabel } from '../../utils/adminLabels';
import { toDateTimeInput } from '../../utils/format';
import FilePicker from './FilePicker.vue';
import SearchSelect from './SearchSelect.vue';
import VideoUploader from './VideoUploader.vue';
import DocumentUploader from './DocumentUploader.vue';
import ImageCropper from './ImageCropper.vue';

const props = defineProps({
  type: { type: String, required: true },
  item: { type: Object, required: true },
  course: { type: Object, default: null },
  focus: { type: String, default: null },
  closeOnSave: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'saved', 'changed']);
const toast = useToast();

const tab = ref('details');
const saving = ref(false);
const busy = ref(false);
const backdropDown = ref(false);

const form = reactive({
  name: '', description: '', image: '', status: 'active', sort_order: 0,
  content: '', content_type: '', video_id: '', document_id: '', required_lesson_id: '',
  require_full_watch: false,
  webinar_link: '', webinar_starts_at: '',
});
const selectedAttachments = ref([]);

const linkedCourses = ref([]);
const courseSearch = ref('');
const courseResults = ref([]);
const searchingCourses = ref(false);
let courseTimer = null;

const imageFile = ref(null);
const imagePreview = ref('');
const imagePreviewUrl = computed(() => imagePreview.value || form.image || '');
const cropFile = ref(null);

function onImagePicked(file) {
  if (file) {
    cropFile.value = file;
  }
}

function onCropConfirm(file) {
  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  cropFile.value = null;
  imageFile.value = file;
  imagePreview.value = URL.createObjectURL(file);
}

function onCropCancel() {
  cropFile.value = null;
}

function clearImage() {
  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  imageFile.value = null;
  imagePreview.value = '';
  form.image = '';
}

const videos = ref([]);
const documents = ref([]);
const attachments = ref([]);

const showVideo = ref(false);
const showDocument = ref(false);
const showAttachment = ref(false);
const newVideo = reactive({ title: '', player: 'vdocipher', code: '', link: '' });
const inlineUploader = ref(null);
const inlineUploadState = ref({ busy: false, canSubmit: false });
const documentUploader = ref(null);
const documentUploadState = ref({ busy: false, canSubmit: false });
const isInlineVdoCipher = computed(() => newVideo.player === 'vdocipher');

const courseLessons = computed(() => {
  const flat = [];

  for (const module of props.course?.modules ?? []) {
    for (const topic of module.topics ?? []) {
      for (const lesson of topic.lessons ?? []) {
        flat.push({ id: lesson.id, name: lesson.name, moduleName: module.name, topicName: topic.name });
      }
    }

    for (const lesson of module.lessons ?? []) {
      flat.push({ id: lesson.id, name: lesson.name, moduleName: module.name, topicName: null });
    }
  }

  return flat;
});

function lessonPath(lesson) {
  return [lesson.moduleName, lesson.topicName, lesson.name].filter(Boolean).join(' › ');
}

const lessonLocation = computed(() => {
  const found = courseLessons.value.find((lesson) => lesson.id === props.item.id);

  return found ? lessonPath({ ...found, name: '' }) : '';
});

const prerequisiteOptions = computed(() => {
  if (props.type !== 'lesson') {
    return [];
  }

  const flat = courseLessons.value;
  const index = flat.findIndex((lesson) => lesson.id === props.item.id);

  return index === -1 ? flat.filter((lesson) => lesson.id !== props.item.id) : flat.slice(0, index);
});

const prerequisiteSelectOptions = computed(() =>
  prerequisiteOptions.value.map((lesson) => ({ value: lesson.id, label: lessonPath(lesson) })),
);

const videoOptions = computed(() => {
  const list = videos.value.map((v) => ({ value: v.id, label: v.title, sub: playerLabel(v.player) }));
  const current = props.item?.video;

  if (current && !list.some((o) => String(o.value) === String(current.id))) {
    list.unshift({ value: current.id, label: current.title, sub: playerLabel(current.player) });
  }

  return list;
});

const documentOptions = computed(() => {
  const list = documents.value.map((d) => ({ value: d.id, label: d.path || d.url }));
  const current = props.item?.document;

  if (current && !list.some((o) => String(o.value) === String(current.id))) {
    list.unshift({ value: current.id, label: current.path || current.url });
  }

  return list;
});

const newAttachment = reactive({ path: '' });
const creatingVideo = ref(false);
const creatingAttachment = ref(false);

const tabLabels = {
  details: 'Detalhes',
  content: 'Conteúdo',
  materials: 'Materiais',
  publish: 'Publicação',
};

const tabs = computed(() => {
  if (props.type === 'lesson') {
    return ['details', 'content', 'materials', 'publish'];
  }

  if (props.type === 'course') {
    return ['details', 'publish'];
  }

  if (props.type === 'module') {
    return ['details', 'courses'];
  }

  return ['details'];
});

const canPublish = computed(() => props.type === 'course' || props.type === 'lesson');
const published = computed(() => form.status === 'active');

function sync() {
  const data = props.item;
  form.name = data.name ?? '';
  form.description = data.description ?? '';
  form.image = data.image ?? '';
  form.status = data.status ?? 'active';

  if (imagePreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(imagePreview.value);
  }

  imageFile.value = null;
  imagePreview.value = data.image_url ?? data.image ?? '';
  form.sort_order = data.sort_order ?? 0;
  form.content = data.content ?? '';
  form.content_type = data.content_type ?? deriveContentType(data);
  form.video_id = data.video?.id ?? '';
  form.document_id = data.document?.id ?? '';
  form.required_lesson_id = data.required_lesson_id ?? '';
  form.require_full_watch = !!data.require_full_watch;
  form.webinar_link = data.webinar_link ?? '';
  form.webinar_starts_at = toDateTimeInput(data.webinar_starts_at);
  selectedAttachments.value = (data.attachments ?? []).map((a) => a.id);
  linkedCourses.value = (data.courses ?? []).map((c) => ({ id: c.id, name: c.name }));
  courseSearch.value = '';
  courseResults.value = [];
  tab.value = 'details';
}

function deriveContentType(data) {
  if (data.content_type) {
    return data.content_type;
  }

  if (data.video) {
    return 'video';
  }

  if (data.document) {
    return 'document';
  }

  if (data.webinar_link) {
    return 'live';
  }

  return '';
}

function setContentType(value) {
  if (value === form.content_type) {
    return;
  }

  form.content_type = value;

  if (value === 'video') {
    form.document_id = '';
    form.webinar_link = '';
    form.content = '';
  } else if (value === 'document') {
    form.video_id = '';
    form.webinar_link = '';
    form.require_full_watch = false;
    form.content = '';
  } else if (value === 'live') {
    form.video_id = '';
    form.document_id = '';
    form.require_full_watch = false;
    form.content = '';
  }
}

async function ensureReferences() {
  if (props.type === 'module') {
    await ensureModuleCourses();
    return;
  }

  if (props.type !== 'lesson' || attachments.value.length) {
    return;
  }

  try {
    const { data } = await api.get('/admin/attachments', { params: { per_page: 100 } });
    attachments.value = data.data ?? [];
  } catch (e) {
    /* silencioso */
  }
}

async function ensureModuleCourses() {
  if (props.item.courses) {
    return;
  }

  try {
    const { data } = await api.get(`/admin/modules/${props.item.id}`);
    linkedCourses.value = (data.data?.courses ?? []).map((c) => ({ id: c.id, name: c.name }));
  } catch (e) {
    /* silencioso */
  }
}

function searchCourses() {
  clearTimeout(courseTimer);
  const term = courseSearch.value.trim();

  if (!term) {
    courseResults.value = [];
    return;
  }

  courseTimer = setTimeout(async () => {
    searchingCourses.value = true;

    try {
      const { data } = await api.get('/admin/courses', { params: { search: term, per_page: 20 } });
      const linked = new Set(linkedCourses.value.map((c) => c.id));
      courseResults.value = (data.data ?? []).filter((c) => !linked.has(c.id));
    } catch (e) {
      toast.error(e?.message || 'Não foi possível buscar cursos.');
    } finally {
      searchingCourses.value = false;
    }
  }, 350);
}

async function linkCourse(course) {
  busy.value = true;

  try {
    await api.post(`/admin/courses/${course.id}/modules`, { module_id: props.item.id });
    linkedCourses.value = [...linkedCourses.value, { id: course.id, name: course.name }];
    courseSearch.value = '';
    courseResults.value = [];
    emit('changed', { type: props.type, id: props.item.id, data: { courses: [...linkedCourses.value] } });
    toast.success('Curso vinculado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível vincular o curso.');
  } finally {
    busy.value = false;
  }
}

async function unlinkCourse(course) {
  busy.value = true;

  try {
    await api.delete(`/admin/courses/${course.id}/modules/${props.item.id}`);
    linkedCourses.value = linkedCourses.value.filter((c) => c.id !== course.id);
    emit('changed', { type: props.type, id: props.item.id, data: { courses: [...linkedCourses.value] } });
    toast.success('Curso desvinculado.');
  } catch (e) {
    toast.error(e?.message || 'Não foi possível desvincular o curso.');
  } finally {
    busy.value = false;
  }
}

const videosLoading = ref(false);
const documentsLoading = ref(false);
let videosRequestId = 0;
let documentsRequestId = 0;

async function searchVideos(query) {
  const id = ++videosRequestId;
  videosLoading.value = true;

  try {
    const { data } = await api.get('/admin/videos', { params: { search: query || undefined, per_page: 20 } });

    if (id !== videosRequestId) {
      return;
    }

    videos.value = data.data ?? [];
  } catch (e) {
    /* silencioso */
  } finally {
    if (id === videosRequestId) {
      videosLoading.value = false;
    }
  }
}

async function searchDocuments(query) {
  const id = ++documentsRequestId;
  documentsLoading.value = true;

  try {
    const { data } = await api.get('/admin/documents', { params: { search: query || undefined, per_page: 20 } });

    if (id !== documentsRequestId) {
      return;
    }

    documents.value = data.data ?? [];
  } catch (e) {
    /* silencioso */
  } finally {
    if (id === documentsRequestId) {
      documentsLoading.value = false;
    }
  }
}

function basePayload() {
  if (props.type === 'course') {
    return { name: form.name, description: form.description || null, image: form.image || null, status: form.status, sort_order: form.sort_order };
  }

  if (props.type === 'module') {
    return { name: form.name, description: form.description || null, image: form.image || null, status: form.status };
  }

  if (props.type === 'topic') {
    return { name: form.name, status: form.status, sort_order: form.sort_order };
  }

  return {
    name: form.name,
    description: form.description || null,
    content: form.content || null,
    module_id: props.item.module_id ?? null,
    topic_id: props.item.topic_id ?? null,
    sort_order: form.sort_order,
    status: form.status,
    content_type: form.content_type || null,
    video_id: form.video_id === '' ? null : Number(form.video_id),
    document_id: form.document_id === '' ? null : Number(form.document_id),
    required_lesson_id: form.required_lesson_id === '' ? null : Number(form.required_lesson_id),
    require_full_watch: !!form.require_full_watch,
    webinar_link: form.webinar_link || null,
    webinar_starts_at: form.webinar_starts_at || null,
    attachment_ids: selectedAttachments.value,
  };
}

const endpoints = { course: 'courses', module: 'modules', topic: 'topics', lesson: 'lessons' };

async function save() {
  if (!form.name.trim()) {
    toast.error('Informe o nome.');
    return;
  }

  saving.value = true;

  try {
    const url = `/admin/${endpoints[props.type]}/${props.item.id}`;
    const payload = basePayload();
    let response;

    if ((props.type === 'course' || props.type === 'module') && imageFile.value) {
      const body = new FormData();

      Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined && typeof value !== 'object') {
          body.append(key, value);
        }
      });

      body.append('image_file', imageFile.value);
      body.append('_method', 'PUT');
      response = await api.post(url, body);
    } else {
      response = await api.put(url, payload);
    }

    let result = response.data.data ?? {};

    if (props.type === 'lesson') {
      result = {
        ...result,
        content_type: form.content_type || null,
        video: form.video_id === '' ? null : videos.value.find((v) => v.id === Number(form.video_id)) ?? null,
        document: form.document_id === '' ? null : documents.value.find((d) => d.id === Number(form.document_id)) ?? null,
        attachments: attachments.value.filter((a) => selectedAttachments.value.includes(a.id)),
      };
    }

    toast.success('Salvo.');
    emit('saved', { type: props.type, id: props.item.id, data: result });

    if (props.closeOnSave) {
      emit('close');
    }
  } catch (e) {
    toast.error(e?.message || 'Não foi possível salvar.');
  } finally {
    saving.value = false;
  }
}

async function togglePublish() {
  busy.value = true;

  try {
    const action = published.value ? 'unpublish' : 'publish';
    const { data } = await api.post(`/admin/${endpoints[props.type]}/${props.item.id}/${action}`);
    emit('saved', { type: props.type, id: props.item.id, data: data.data ?? {} });
  } catch (e) {
    toast.error(e?.message || 'Não foi possível alterar a publicação.');
  } finally {
    busy.value = false;
  }
}

async function createVideo() {
  if (!newVideo.title.trim()) {
    toast.error('Informe o título do vídeo.');
    return;
  }

  creatingVideo.value = true;

  try {
    const { data } = await api.post('/admin/videos', {
      title: newVideo.title.trim(),
      player: newVideo.player,
      code: newVideo.code || null,
      link: newVideo.link || null,
      status: 'active',
    });
    videos.value = [...videos.value, data.data];
    form.video_id = data.data.id;
    newVideo.title = '';
    newVideo.code = '';
    newVideo.link = '';
    showVideo.value = false;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível criar o vídeo.');
  } finally {
    creatingVideo.value = false;
  }
}

function onVideoUploaded(video) {
  videos.value = [...videos.value, video];
  form.video_id = video.id;
  newVideo.title = '';
  newVideo.code = '';
  newVideo.link = '';
  showVideo.value = false;
}

function onDocumentUploaded(document) {
  documents.value = [...documents.value, document];
  form.document_id = document.id;
  showDocument.value = false;
}

async function createAttachment() {
  if (!newAttachment.path.trim()) {
    toast.error('Informe o caminho do anexo.');
    return;
  }

  creatingAttachment.value = true;

  try {
    const { data } = await api.post('/admin/attachments', {
      path: newAttachment.path.trim(),
      status: 'active',
    });
    attachments.value = [...attachments.value, data.data];
    selectedAttachments.value = [...selectedAttachments.value, data.data.id];
    newAttachment.path = '';
    showAttachment.value = false;
  } catch (e) {
    toast.error(e?.message || 'Não foi possível criar o anexo.');
  } finally {
    creatingAttachment.value = false;
  }
}

watch(() => props.item, sync, { immediate: true });
watch(() => props.type, ensureReferences);

function onKeydown(e) {
  if (e.key === 'Escape') {
    emit('close');
  }
}

function onBackdropDown(e) {
  backdropDown.value = e.target === e.currentTarget;
}

const focusTargets = {
  name: 'dw-name',
  description: 'dw-desc',
  content: 'dw-content',
};

function focusField() {
  if (props.focus === 'content') {
    tab.value = 'content';
  }

  const id = focusTargets[props.focus];

  if (id) {
    document.getElementById(id)?.focus();
  }
}

onMounted(() => {
  ensureReferences();
  document.addEventListener('keydown', onKeydown);
  nextTick(focusField);
});
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.drawer-divider{height:1px;background:var(--border);margin:4px 0;}
.dw-list{display:flex;flex-direction:column;gap:8px;}
.dw-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--bg-card-alt);}
.dw-info{display:flex;flex-direction:column;gap:1px;min-width:0;}
.dw-row-name{font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.dw-row-sub{font-size:11px;color:var(--text-muted);}
.drawer-link{
  align-self:flex-start;background:none;border:none;color:var(--accent);font-weight:700;font-size:12.5px;cursor:pointer;
  padding:2px 0;font-family:inherit;
}
.dw-seg{display:flex;flex-wrap:wrap;gap:6px;background:var(--bg-card-alt);border:1px solid var(--border);border-radius:12px;padding:4px;}
.dw-seg-btn{
  flex:1 1 auto;border:none;background:none;color:var(--text-muted);font-family:inherit;font-weight:700;font-size:12.5px;
  padding:8px 10px;border-radius:9px;cursor:pointer;transition:background .15s ease,color .15s ease;white-space:nowrap;
}
.dw-seg-btn:hover{color:var(--text-primary);}
.dw-seg-btn.active{background:var(--accent);color:var(--accent-ink);}
.drawer-inline{
  display:flex;flex-direction:column;gap:8px;background:var(--bg-card-alt);border:1px solid var(--border);
  border-radius:12px;padding:12px;
}
.drawer-checks{display:flex;flex-direction:column;gap:8px;max-height:220px;overflow:auto;}
.dw-image-preview{position:relative;aspect-ratio:16/9;margin-bottom:8px;border-radius:10px;overflow:hidden;border:1px solid var(--border);}
.dw-image-preview img{display:block;width:100%;height:100%;object-fit:cover;}
.dw-image-remove{
  position:absolute;top:8px;right:8px;background:rgba(0,0,0,.62);color:#fff;border:none;border-radius:8px;
  padding:4px 9px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:inherit;
}
.form-group > .file-picker{margin-bottom:8px;}

.dw-option{
  display:flex;align-items:flex-start;gap:10px;padding:12px;margin-top:2px;
  border:1px solid var(--border);border-radius:12px;background:var(--bg-card-alt);
  cursor:pointer;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease;
}
.dw-option:hover{border-color:var(--accent);}
.dw-option.is-on{border-color:var(--accent);background:var(--accent-soft);}
.dw-option input[type="checkbox"]{
  appearance:none;-webkit-appearance:none;margin:1px 0 0;flex-shrink:0;
  width:18px;height:18px;display:inline-grid;place-content:center;cursor:pointer;
  border:1.5px solid var(--border);border-radius:6px;background:var(--bg-input);
  transition:border-color .14s ease,background .14s ease;
}
.dw-option input[type="checkbox"]::before{
  content:"";width:10px;height:10px;transform:scale(0);transition:transform .12s ease;
  box-shadow:inset 1em 1em var(--accent-ink);
  clip-path:polygon(14% 44%,0 65%,50% 100%,100% 16%,80% 0%,43% 62%);
}
.dw-option:hover input[type="checkbox"]{border-color:var(--accent);}
.dw-option input[type="checkbox"]:checked{background:var(--accent);border-color:var(--accent);}
.dw-option input[type="checkbox"]:checked::before{transform:scale(1);}
.dw-option input[type="checkbox"]:focus-visible{outline:2px solid var(--accent);outline-offset:2px;}
.dw-option-text{display:flex;flex-direction:column;gap:3px;min-width:0;}
.dw-option-title{font-size:13px;font-weight:700;color:var(--text-primary);}
.dw-option-desc{font-size:11.5px;line-height:1.45;color:var(--text-muted);font-weight:400;}
</style>
