<template>
  <section class="cb">
    <header class="cb-bar">
      <div class="cb-bar-lead">
        <label v-if="selectable" class="cb-check">
          <input
            class="admin-check"
            type="checkbox"
            :checked="allSelected"
            :indeterminate.prop="partial"
            aria-label="Selecionar todos os módulos"
            @change="emit('toggle-select-all', $event.target.checked)"
          >
        </label>
        <div class="cb-bar-text">
          <span class="cb-bar-title">{{ headerTitle }}</span>
          <span v-if="headerSub" class="cb-bar-sub">{{ headerSub }}</span>
        </div>
      </div>
      <div class="cb-bar-actions">
        <button v-if="showAttach" class="btn btn-outline btn-sm" @click="emit('attach-module')">Vincular módulo</button>
        <button v-if="showAddModule" class="btn btn-primary btn-sm" @click="emit('add-module')">+ Módulo</button>
      </div>
    </header>

    <draggable
      v-model="modules"
      item-key="id"
      handle=".cb-handle"
      :animation="160"
      :disabled="!allowModuleReorder"
      ghost-class="cb-ghost"
      class="cb-modules"
      @end="reorder('module', course?.id, modules)"
    >
      <template #item="{ element: module }">
        <article class="cb-module">
          <div
            class="cb-row cb-row-module"
            :class="{ active: isSelected('module', module.id) }"
            @click="emit('select', { type: 'module', id: module.id })"
          >
            <label v-if="selectable" class="cb-check" @click.stop>
              <input
                class="admin-check"
                type="checkbox"
                :checked="isModuleSelected(module.id)"
                :aria-label="`Selecionar ${module.name}`"
                @change="emit('toggle-select', module.id)"
              >
            </label>
            <span v-if="allowModuleReorder" class="cb-handle" title="Arrastar" @click.stop>⠿</span>
            <button class="cb-caret" :class="{ open: !isCollapsed(`m-${module.id}`) }" @click.stop="toggleCollapse(`m-${module.id}`)">▸</button>

            <input
              v-if="isEditing('module', module.id)"
              :ref="setRenameInput"
              class="cb-name-input"
              v-model="editValue"
              @click.stop
              @focus="$event.target.select()"
              @keydown.enter.prevent="commitRename('module', module)"
              @keydown.esc.prevent="cancelRename"
              @blur="commitRename('module', module)"
            >
            <span v-else class="cb-name">{{ module.name }}</span>

            <span v-if="module.status !== 'active'" class="cb-chip draft">Rascunho</span>
            <span class="cb-count">{{ module.topics?.length ?? 0 }} tópicos · {{ moduleLessonCount(module) }} aulas</span>

            <span class="cb-menu-wrap" @click.stop>
              <button class="cb-dots" @click="toggleMenu(`m-${module.id}`)" title="Ações">⋯</button>
              <div v-if="openMenu === `m-${module.id}`" class="cb-menu">
                <button @click="startRename('module', module)">Renomear</button>
                <button @click="run(() => emit('add-topic', module))">Adicionar tópico</button>
                <button class="danger" @click="run(() => emit('remove', { type: 'module', node: module }))">Remover</button>
              </div>
            </span>
          </div>

          <div v-if="!isCollapsed(`m-${module.id}`)" class="cb-children">
            <draggable
              v-model="module.topics"
              item-key="id"
              handle=".cb-handle"
              :animation="160"
              ghost-class="cb-ghost"
              class="cb-topics"
              @end="reorder('topic', module.id, module.topics)"
            >
              <template #item="{ element: topic }">
                <div class="cb-topic">
                  <div
                    class="cb-row cb-row-topic"
                    :class="{ active: isSelected('topic', topic.id) }"
                    @click="emit('select', { type: 'topic', id: topic.id })"
                  >
                    <span class="cb-handle" title="Arrastar" @click.stop>⠿</span>
                    <button class="cb-caret" :class="{ open: !isCollapsed(`t-${topic.id}`) }" @click.stop="toggleCollapse(`t-${topic.id}`)">▸</button>

                    <input
                      v-if="isEditing('topic', topic.id)"
                      :ref="setRenameInput"
                      class="cb-name-input"
                      v-model="editValue"
                      @click.stop
                      @focus="$event.target.select()"
                      @keydown.enter.prevent="commitRename('topic', topic)"
                      @keydown.esc.prevent="cancelRename"
                      @blur="commitRename('topic', topic)"
                    >
                    <span v-else class="cb-name">{{ topic.name }}</span>

                    <span v-if="topic.status !== 'active'" class="cb-chip draft">Rascunho</span>

                    <span class="cb-menu-wrap" @click.stop>
                      <button class="cb-dots" @click="toggleMenu(`t-${topic.id}`)" title="Ações">⋯</button>
                      <div v-if="openMenu === `t-${topic.id}`" class="cb-menu">
                        <button @click="startRename('topic', topic)">Renomear</button>
                        <button @click="run(() => emit('add-lesson', topic))">Adicionar aula</button>
                        <button class="danger" @click="run(() => emit('remove', { type: 'topic', node: topic }))">Remover</button>
                      </div>
                    </span>
                  </div>

                  <div v-if="!isCollapsed(`t-${topic.id}`)" class="cb-children cb-lessons-wrap">
                    <draggable
                      v-model="topic.lessons"
                      item-key="id"
                      handle=".cb-handle"
                      :animation="160"
                      ghost-class="cb-ghost"
                      class="cb-lessons"
                      :group="lessonGroup(module.id)"
                      @change="onLessonChange($event, 'lesson', topic.id, topic.lessons)"
                    >
                      <template #item="{ element: lesson }">
                        <div
                          class="cb-row cb-row-lesson"
                          :class="{ active: isSelected('lesson', lesson.id) }"
                          @click="emit('select', { type: 'lesson', id: lesson.id })"
                        >
                          <span class="cb-handle" title="Arrastar" @click.stop>⠿</span>
                          <span class="cb-dot" :class="liveState(lesson) || 'idle'"></span>

                          <input
                            v-if="isEditing('lesson', lesson.id)"
                            :ref="setRenameInput"
                            class="cb-name-input"
                            v-model="editValue"
                            @click.stop
                            @focus="$event.target.select()"
                            @keydown.enter.prevent="commitRename('lesson', lesson)"
                            @keydown.esc.prevent="cancelRename"
                            @blur="commitRename('lesson', lesson)"
                          >
                          <span v-else class="cb-name">{{ lesson.name }}</span>

                          <span v-if="liveState(lesson) === 'live'" class="cb-chip live"><i></i>Ao vivo</span>
                          <span v-else-if="liveState(lesson) === 'soon'" class="cb-chip soon">Em breve</span>
                          <span v-if="lesson.video" class="cb-chip">Vídeo</span>
                          <span v-if="lesson.document" class="cb-chip">Doc</span>
                          <span v-if="lesson.attachments?.length" class="cb-chip">{{ lesson.attachments.length }} anexo(s)</span>
                          <span v-if="lesson.required_lesson_id" class="cb-chip req">Pré-req</span>
                          <span v-if="lesson.status !== 'active'" class="cb-chip draft">Rascunho</span>

                          <span class="cb-menu-wrap" @click.stop>
                            <button class="cb-dots" @click="toggleMenu(`l-${lesson.id}`)" title="Ações">⋯</button>
                            <div v-if="openMenu === `l-${lesson.id}`" class="cb-menu">
                              <button @click="startRename('lesson', lesson)">Renomear</button>
                              <button class="danger" @click="run(() => emit('remove', { type: 'lesson', node: lesson }))">Remover</button>
                            </div>
                          </span>
                        </div>
                      </template>
                    </draggable>

                    <p v-if="!topic.lessons?.length" class="cb-empty">Nenhuma aula ainda.</p>
                    <button class="cb-add" @click.stop="emit('add-lesson', topic)">+ Adicionar aula</button>
                  </div>
                </div>
              </template>
            </draggable>

            <p v-if="!module.topics?.length" class="cb-empty">Nenhum tópico ainda.</p>
            <button class="cb-add" @click.stop="emit('add-topic', module)">+ Adicionar tópico</button>

            <draggable
              v-model="module.lessons"
              item-key="id"
              handle=".cb-handle"
              :animation="160"
              ghost-class="cb-ghost"
              class="cb-lessons cb-module-lessons"
              :class="{ 'cb-dropzone-empty': !module.lessons?.length }"
              :group="lessonGroup(module.id)"
              @change="onLessonChange($event, 'module-lesson', module.id, module.lessons)"
            >
              <template #item="{ element: lesson }">
                <div
                  class="cb-row cb-row-lesson"
                  :class="{ active: isSelected('lesson', lesson.id) }"
                  @click="emit('select', { type: 'lesson', id: lesson.id })"
                >
                  <span class="cb-handle" title="Arrastar" @click.stop>⠿</span>
                  <span class="cb-dot" :class="liveState(lesson) || 'idle'"></span>

                  <input
                    v-if="isEditing('lesson', lesson.id)"
                    :ref="setRenameInput"
                    class="cb-name-input"
                    v-model="editValue"
                    @click.stop
                    @focus="$event.target.select()"
                    @keydown.enter.prevent="commitRename('lesson', lesson)"
                    @keydown.esc.prevent="cancelRename"
                    @blur="commitRename('lesson', lesson)"
                  >
                  <span v-else class="cb-name">{{ lesson.name }}</span>

                  <span v-if="liveState(lesson) === 'live'" class="cb-chip live"><i></i>Ao vivo</span>
                  <span v-else-if="liveState(lesson) === 'soon'" class="cb-chip soon">Em breve</span>
                  <span v-if="lesson.video" class="cb-chip">Vídeo</span>
                  <span v-if="lesson.document" class="cb-chip">Doc</span>
                  <span v-if="lesson.attachments?.length" class="cb-chip">{{ lesson.attachments.length }} anexo(s)</span>
                  <span v-if="lesson.required_lesson_id" class="cb-chip req">Pré-req</span>
                  <span v-if="lesson.status !== 'active'" class="cb-chip draft">Rascunho</span>

                  <span class="cb-menu-wrap" @click.stop>
                    <button class="cb-dots" @click="toggleMenu(`l-${lesson.id}`)" title="Ações">⋯</button>
                    <div v-if="openMenu === `l-${lesson.id}`" class="cb-menu">
                      <button @click="startRename('lesson', lesson)">Renomear</button>
                      <button class="danger" @click="run(() => emit('remove', { type: 'lesson', node: lesson }))">Remover</button>
                    </div>
                  </span>
                </div>
              </template>
            </draggable>

            <p v-if="!module.lessons?.length" class="cb-drop-hint">Arraste uma aula para cá para retirá-la do tópico.</p>
            <button class="cb-add" @click.stop="emit('add-module-lesson', module)">+ Adicionar aula</button>
          </div>
        </article>
      </template>
    </draggable>

    <div v-if="!modules.length" class="cb-empty-root">
      <strong>{{ emptyTitle }}</strong>
      <span>{{ emptyHint }}</span>
      <div class="cb-empty-actions">
        <button v-if="showAddModule" class="btn btn-primary btn-sm" @click="emit('add-module')">+ Módulo</button>
        <button v-if="showAttach" class="btn btn-outline btn-sm" @click="emit('attach-module')">Vincular módulo</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
  course: { type: Object, default: null },
  modules: { type: Array, default: null },
  selected: { type: Object, default: null },
  title: { type: String, default: null },
  subtitle: { type: String, default: null },
  showAttach: { type: Boolean, default: true },
  showAddModule: { type: Boolean, default: true },
  allowModuleReorder: { type: Boolean, default: true },
  emptyTitle: { type: String, default: null },
  emptyHint: { type: String, default: null },
  selectable: { type: Boolean, default: false },
  selectedIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['select', 'rename', 'add-module', 'attach-module', 'add-topic', 'add-lesson', 'add-module-lesson', 'remove', 'reorder', 'update:modules', 'toggle-select', 'toggle-select-all']);

const collapsed = reactive({});
const openMenu = ref(null);
const editing = ref(null);
const editValue = ref('');
const renameInput = ref(null);

function setRenameInput(el) {
  if (el) {
    renameInput.value = el;
  }
}

const modules = computed({
  get: () => props.modules ?? props.course?.modules ?? [],
  set: (value) => {
    if (props.modules) {
      emit('update:modules', value);
    } else if (props.course) {
      props.course.modules = value;
    }
  },
});

const headerTitle = computed(() => props.title ?? props.course?.name ?? '');
const moduleCount = computed(() => modules.value.length);
const lessonCount = computed(() =>
  modules.value.reduce((total, module) => total + moduleLessonCount(module), 0),
);
const headerSub = computed(() => props.subtitle ?? `${moduleCount.value} módulo(s) · ${lessonCount.value} aula(s)`);
const emptyTitle = computed(() => props.emptyTitle ?? 'Sem conteúdo ainda');
const emptyHint = computed(() => props.emptyHint ?? (props.showAttach
  ? 'Comece adicionando um módulo ou vinculando um existente.'
  : 'Crie o primeiro módulo para começar.'));

const allSelected = computed(() => {
  const ids = modules.value.map((module) => module.id);

  return ids.length > 0 && ids.every((id) => props.selectedIds.includes(id));
});

const partial = computed(() => {
  const ids = modules.value.map((module) => module.id);
  const selected = ids.filter((id) => props.selectedIds.includes(id)).length;

  return selected > 0 && selected < ids.length;
});

function isModuleSelected(id) {
  return props.selectedIds.includes(id);
}

function moduleLessonCount(module) {
  return (module.topics ?? []).reduce((sum, topic) => sum + (topic.lessons?.length ?? 0), 0)
    + (module.lessons?.length ?? 0);
}

function isCollapsed(key) {
  return collapsed[key] !== false;
}

function toggleCollapse(key) {
  collapsed[key] = !isCollapsed(key);
}

function isSelected(type, id) {
  return props.selected?.type === type && props.selected?.id === id;
}

function isEditing(type, id) {
  return editing.value === `${type}-${id}`;
}

function startRename(type, node) {
  openMenu.value = null;
  editing.value = `${type}-${node.id}`;
  editValue.value = node.name;
  nextTick(() => renameInput.value?.focus());
}

function commitRename(type, node) {
  if (!isEditing(type, node.id)) {
    return;
  }

  const name = editValue.value.trim();
  editing.value = null;

  if (name && name !== node.name) {
    emit('rename', { type, id: node.id, name });
  }
}

function cancelRename() {
  editing.value = null;
}

function toggleMenu(key) {
  openMenu.value = openMenu.value === key ? null : key;
}

function run(action) {
  openMenu.value = null;
  action();
}

function reorder(level, parentId, list) {
  emit('reorder', { level, parentId, ids: list.map((item) => item.id) });
}

function lessonGroup(moduleId) {
  return { name: `cb-lessons-${moduleId}` };
}

function onLessonChange(event, level, parentId, list) {
  if (!event.added && !event.moved && !event.removed) {
    return;
  }

  if (event.added) {
    event.added.element.topic_id = level === 'lesson' ? parentId : null;
  }

  if (list?.length) {
    reorder(level, parentId, list);
  }
}

function liveState(lesson) {
  if (!lesson.webinar_link) {
    return null;
  }

  if (lesson.webinar_starts_at && new Date(lesson.webinar_starts_at).getTime() > Date.now()) {
    return 'soon';
  }

  return 'live';
}

function closeMenus() {
  openMenu.value = null;
}

onMounted(() => document.addEventListener('click', closeMenus));
onBeforeUnmount(() => document.removeEventListener('click', closeMenus));
</script>

<style scoped>
.cb{--lvl-module:#17c993;--lvl-topic:#3b82f6;--lvl-lesson:#a78bfa;display:flex;flex-direction:column;gap:14px;width:100%;max-width:1440px;}
.cb-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.cb-bar-lead{display:flex;align-items:center;gap:10px;}
.cb-bar-text{display:flex;flex-direction:column;gap:1px;}
.cb-check{display:inline-flex;align-items:center;flex-shrink:0;cursor:pointer;}
.cb-bar-title{font-size:15px;font-weight:800;}
.cb-bar-sub{font-size:11.5px;color:var(--text-muted);}
.cb-bar-actions{display:flex;gap:8px;}

.cb-modules{display:flex;flex-direction:column;gap:12px;}
.cb-topics,.cb-lessons{display:flex;flex-direction:column;gap:6px;min-height:6px;}
.cb-module-lessons.cb-dropzone-empty{min-height:34px;margin-left:20px;border:1px dashed var(--border);border-radius:10px;background:var(--bg-card-alt);}
.cb-drop-hint{font-size:11.5px;color:var(--text-muted);margin:0 0 0 20px;padding:0;}

.cb-module{background:var(--bg-card);border:1px solid var(--border);border-left:4px solid var(--lvl-module);border-radius:14px;overflow:visible;}
.cb-row{
  display:flex;align-items:center;gap:8px;padding:9px 12px;cursor:pointer;
  border-bottom:1px solid transparent;transition:background .14s ease;
}
.cb-row:hover{background:var(--bg-card-alt);}
.cb-row.active{background:var(--accent-soft);}
.cb-row-module{padding:12px;font-weight:700;border-radius:14px 14px 0 0;}
.cb-row-topic,.cb-row-lesson{border-radius:10px;border:1px solid var(--border);background:var(--bg-card);}
.cb-row-topic{margin-left:4px;border-left:3px solid var(--lvl-topic);}
.cb-row-lesson{margin-left:20px;border-left:3px solid var(--lvl-lesson);}

.cb-handle{color:var(--text-muted);cursor:grab;font-size:13px;line-height:1;user-select:none;opacity:0;transition:opacity .14s ease;}
.cb-handle:active{cursor:grabbing;}
.cb-row:hover .cb-handle{opacity:1;}
.cb-caret{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:11px;padding:2px;font-family:inherit;transition:transform .16s ease;}
.cb-caret.open{transform:rotate(90deg);}
.cb-name{flex:1;min-width:0;font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cb-name-input{
  flex:1;min-width:0;font-size:13.5px;font-weight:600;font-family:inherit;color:var(--text-primary);
  background:var(--bg-input);border:1px solid var(--accent);border-radius:8px;padding:3px 8px;outline:none;
}
.cb-count{font-size:11px;color:var(--text-muted);flex-shrink:0;}

.cb-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;background:var(--border);}
.cb-dot.live{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.18);}
.cb-dot.soon{background:#f59e0b;}

.cb-chip{
  font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.3px;
  background:var(--bg-card-alt);color:var(--text-secondary);flex-shrink:0;display:inline-flex;align-items:center;gap:4px;
}
.cb-chip.live{background:rgba(239,68,68,.15);color:#ef4444;}
.cb-chip.soon{background:rgba(245,158,11,.15);color:#f59e0b;}
.cb-chip.draft{background:rgba(148,163,184,.18);color:#94a3b8;}
.cb-chip.req{background:rgba(59,130,246,.15);color:#3b82f6;}
.cb-chip i{width:6px;height:6px;border-radius:50%;background:currentColor;}

.cb-menu-wrap{position:relative;flex-shrink:0;}
.cb-dots{
  background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:16px;line-height:1;padding:2px 6px;border-radius:8px;
  font-family:inherit;opacity:0;transition:opacity .14s ease,background .14s ease;
}
.cb-row:hover .cb-dots,.cb-dots:focus{opacity:1;}
.cb-dots:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.cb-menu{
  position:absolute;right:0;top:calc(100% + 6px);z-index:20;min-width:168px;padding:6px;
  background:var(--bg-card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--shadow);
  display:flex;flex-direction:column;gap:2px;
}
.cb-menu button{
  text-align:left;background:none;border:none;color:var(--text-secondary);font-family:inherit;font-size:12.5px;font-weight:600;
  padding:8px 10px;border-radius:8px;cursor:pointer;
}
.cb-menu button:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.cb-menu button.danger{color:#ef4444;}
.cb-menu button.danger:hover{background:rgba(239,68,68,.12);}

.cb-children{padding:10px 12px 12px 14px;border-top:1px dashed var(--border);display:flex;flex-direction:column;gap:6px;}
.cb-lessons-wrap{padding:8px 0 4px 12px;border-top:none;border-left:2px solid var(--border);margin-left:8px;}

.cb-add{
  align-self:flex-start;background:none;border:none;color:var(--accent);font-weight:700;font-size:12px;cursor:pointer;
  padding:6px 8px;border-radius:8px;font-family:inherit;
}
.cb-add:hover{background:var(--accent-soft);}
.cb-empty{font-size:12px;color:var(--text-muted);padding:2px 8px;margin:0;}
.cb-empty-root{
  display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:36px 20px;
  border:1px dashed var(--border);border-radius:14px;background:var(--bg-card);
}
.cb-empty-root strong{font-size:14px;}
.cb-empty-root span{font-size:12.5px;color:var(--text-muted);}
.cb-empty-actions{display:flex;gap:8px;margin-top:8px;}
.cb-ghost{opacity:.5;}
</style>
