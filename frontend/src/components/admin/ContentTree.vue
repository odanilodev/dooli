<template>
  <div class="ct">

    <div class="ct-head">
      <div class="ct-head-text" :class="{ active: isSelected('course', course.id) }" @click="select('course', course)">
        <strong>{{ course.name }}</strong>
        <span class="ct-head-sub">{{ moduleCount }} módulo(s) · {{ lessonCount }} aula(s) · editar curso</span>
      </div>
      <div class="ct-head-actions">
        <button class="btn btn-outline btn-sm" @click="emit('attach-module')">Vincular</button>
        <button class="btn btn-primary btn-sm" @click="emit('create-module')">+ Módulo</button>
      </div>
    </div>

    <div class="ct-tree">
      <draggable
        v-model="modules"
        item-key="id"
        handle=".ct-handle"
        :animation="150"
        ghost-class="ct-ghost"
        class="ct-list"
        @end="emitReorder('module', course.id, modules)"
      >
        <template #item="{ element: module }">
          <div class="ct-node">
            <div class="ct-row" :class="{ active: isSelected('module', module.id) }" @click="select('module', module)">
              <span class="ct-handle" title="Arrastar" @click.stop>⠿</span>
              <button class="ct-toggle" @click.stop="toggle(`m-${module.id}`)">{{ isOpen(`m-${module.id}`) ? '▾' : '▸' }}</button>
              <span class="ct-name">{{ module.name }}</span>
              <span class="status-pill" :class="module.status">{{ module.status }}</span>
              <div class="ct-actions">
                <button class="btn btn-outline btn-sm" @click.stop="emit('create-topic', module)">+ tópico</button>
                <button class="btn btn-danger btn-sm" @click.stop="emit('remove-node', { type: 'module', node: module })">remover</button>
              </div>
            </div>

            <div v-if="isOpen(`m-${module.id}`)" class="ct-children">
              <draggable
                v-model="module.topics"
                item-key="id"
                handle=".ct-handle"
                :animation="150"
                ghost-class="ct-ghost"
                class="ct-list"
                @end="emitReorder('topic', module.id, module.topics)"
              >
                <template #item="{ element: topic }">
                  <div class="ct-node">
                    <div class="ct-row ct-row-topic" :class="{ active: isSelected('topic', topic.id) }" @click="select('topic', topic)">
                      <span class="ct-handle" title="Arrastar" @click.stop>⠿</span>
                      <button class="ct-toggle" @click.stop="toggle(`t-${topic.id}`)">{{ isOpen(`t-${topic.id}`) ? '▾' : '▸' }}</button>
                      <span class="ct-name">{{ topic.name }}</span>
                      <span class="status-pill" :class="topic.status">{{ topic.status }}</span>
                      <div class="ct-actions">
                        <button class="btn btn-outline btn-sm" @click.stop="emit('create-lesson', topic)">+ aula</button>
                        <button class="btn btn-danger btn-sm" @click.stop="emit('remove-node', { type: 'topic', node: topic })">remover</button>
                      </div>
                    </div>

                    <div v-if="isOpen(`t-${topic.id}`)" class="ct-children">
                      <draggable
                        v-model="topic.lessons"
                        item-key="id"
                        handle=".ct-handle"
                        :animation="150"
                        ghost-class="ct-ghost"
                        class="ct-list"
                        @end="emitReorder('lesson', topic.id, topic.lessons)"
                      >
                        <template #item="{ element: lesson }">
                          <div class="ct-row ct-row-lesson" :class="{ active: isSelected('lesson', lesson.id) }" @click="select('lesson', lesson)">
                            <span class="ct-handle" title="Arrastar" @click.stop>⠿</span>
                            <span class="ct-name">{{ lesson.name }}</span>
                            <span v-if="lesson.video" class="ct-badge">vídeo</span>
                            <span v-if="lesson.webinar_link" class="ct-badge live">ao vivo</span>
                            <span class="status-pill" :class="lesson.status">{{ lesson.status }}</span>
                            <div class="ct-actions">
                              <button class="btn btn-danger btn-sm" @click.stop="emit('remove-node', { type: 'lesson', node: lesson })">remover</button>
                            </div>
                          </div>
                        </template>
                      </draggable>
                      <p v-if="!topic.lessons?.length" class="ct-empty">Nenhuma aula — clique em "+ aula".</p>
                    </div>
                  </div>
                </template>
              </draggable>
              <p v-if="!module.topics?.length" class="ct-empty">Nenhum tópico — clique em "+ tópico".</p>
            </div>
          </div>
        </template>
      </draggable>

      <p v-if="!modules.length" class="ct-empty ct-empty-root">
        Nenhum módulo vinculado. Use "Vincular" ou "+ Módulo".
      </p>
    </div>

  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps({
  course: { type: Object, required: true },
  selected: { type: Object, default: null },
});

const emit = defineEmits(['select', 'create-module', 'attach-module', 'create-topic', 'create-lesson', 'remove-node', 'reorder']);

const open = reactive({});

const modules = computed({
  get: () => props.course.modules ?? [],
  set: (value) => {
    props.course.modules = value;
  },
});

const moduleCount = computed(() => (props.course.modules ?? []).length);
const lessonCount = computed(() =>
  (props.course.modules ?? []).reduce(
    (total, module) => total + (module.topics ?? []).reduce((sum, topic) => sum + (topic.lessons?.length ?? 0), 0),
    0,
  ),
);

function isOpen(key) {
  return open[key] !== false;
}

function toggle(key) {
  open[key] = !isOpen(key);
}

function isSelected(type, id) {
  return props.selected?.type === type && props.selected?.id === id;
}

function select(type, node) {
  emit('select', { type, id: node.id });
}

function emitReorder(level, parentId, list) {
  emit('reorder', { level, parentId, ids: list.map((item) => item.id) });
}

watch(
  () => props.course?.id,
  () => {
    for (const module of props.course?.modules ?? []) {
      open[`m-${module.id}`] = true;
      for (const topic of module.topics ?? []) {
        open[`t-${topic.id}`] = true;
      }
    }
  },
  { immediate: true },
);
</script>

<style scoped>
.ct{display:flex;flex-direction:column;gap:12px;}
.ct-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.ct-head-text{display:flex;flex-direction:column;gap:2px;min-width:0;cursor:pointer;padding:6px 10px;border-radius:10px;border:1px solid transparent;}
.ct-head-text:hover{border-color:var(--accent);}
.ct-head-text.active{border-color:var(--accent);background:var(--accent-soft);}
.ct-head-text strong{font-size:14px;}
.ct-head-sub{font-size:11px;color:var(--text-muted);}
.ct-head-actions{display:flex;gap:8px;}
.ct-tree{display:flex;flex-direction:column;gap:6px;}
.ct-list{display:flex;flex-direction:column;gap:6px;min-height:6px;}
.ct-node{display:flex;flex-direction:column;gap:6px;}
.ct-row{
  display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border);border-radius:11px;
  background:var(--bg-card);cursor:pointer;transition:border-color .14s ease,background .14s ease;
}
.ct-row:hover{border-color:var(--accent);}
.ct-row.active{border-color:var(--accent);background:var(--accent-soft);}
.ct-row-topic{margin-left:14px;}
.ct-row-lesson{margin-left:28px;}
.ct-handle{color:var(--text-muted);cursor:grab;font-size:13px;line-height:1;user-select:none;}
.ct-handle:active{cursor:grabbing;}
.ct-toggle{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:11px;padding:0 2px;font-family:inherit;}
.ct-name{flex:1;min-width:0;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ct-actions{display:flex;gap:6px;flex-shrink:0;}
.ct-children{display:flex;flex-direction:column;gap:6px;padding-left:10px;border-left:2px solid var(--border);margin-left:8px;}
.ct-badge{font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;background:var(--bg-card-alt);color:var(--text-secondary);text-transform:uppercase;letter-spacing:.3px;}
.ct-badge.live{background:rgba(239,68,68,.15);color:#ef4444;}
.ct-empty{font-size:12px;color:var(--text-muted);padding:6px 10px;margin:0;}
.ct-empty-root{padding:18px;text-align:center;border:1px dashed var(--border);border-radius:12px;}
.ct-ghost{opacity:.5;}
.btn-sm{padding:6px 10px;font-size:11.5px;border-radius:8px;}
.status-pill{font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;text-transform:uppercase;letter-spacing:.3px;flex-shrink:0;}
.status-pill.active{background:rgba(34,197,94,.15);color:#22c55e;}
.status-pill.inactive{background:rgba(148,163,184,.18);color:#94a3b8;}
</style>
