<template>
  <div v-if="teams.length > 1" class="team-switch" :class="{ open }" ref="root">
    <button class="team-trigger" type="button" @click="open = !open">
      <svg class="team-icon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="team-text">
        <span class="team-label">Turma</span>
        <span class="team-name">{{ current?.name || 'Selecionar' }}</span>
      </span>
      <svg class="team-chevron" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
    </button>

    <div class="team-dropdown">
      <button
        v-for="team in teams"
        :key="team.id"
        type="button"
        class="team-option"
        :class="{ active: team.id === selectedTeamId }"
        @click="choose(team.id)"
      >
        <span class="team-option-name">{{ team.name }}</span>
        <span v-if="team.courses_count != null" class="team-option-count">{{ team.courses_count }} curso(s)</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useStudentTeamsStore } from '../stores/studentTeams';
import { useCoursesStore } from '../stores/courses';
import { useCoursePlayerStore } from '../stores/coursePlayer';

const teamsStore = useStudentTeamsStore();
const courses = useCoursesStore();
const player = useCoursePlayerStore();

const root = ref(null);
const open = ref(false);

const teams = computed(() => teamsStore.teams);
const selectedTeamId = computed(() => teamsStore.selectedTeamId);
const current = computed(() => teams.value.find((team) => team.id === selectedTeamId.value) ?? null);

function choose(teamId) {
  open.value = false;

  if (teamId === selectedTeamId.value) {
    return;
  }

  teamsStore.selectTeam(teamId);
  player.reset();
  courses.fetchMyCourses(true);
  courses.fetchContinueWatching();
}

function onClickOutside(event) {
  if (root.value && !root.value.contains(event.target)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<style scoped>
.team-switch{position:relative;}
.team-trigger{
  display:flex;align-items:center;gap:10px;cursor:pointer;background:var(--bg-card-alt);
  border:1px solid var(--border);color:inherit;padding:6px 12px;border-radius:10px;
  transition:border-color .15s ease;
}
.team-trigger:hover{border-color:var(--accent);}
.team-icon{color:var(--text-muted);flex-shrink:0;}
.team-text{display:flex;flex-direction:column;line-height:1.15;text-align:left;}
.team-label{font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:700;}
.team-name{font-size:13px;font-weight:700;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.team-chevron{transition:transform .15s ease;color:var(--text-muted);flex-shrink:0;}
.team-switch.open .team-chevron{transform:rotate(180deg);}
.team-dropdown{
  position:absolute;right:0;top:calc(100% + 10px);background:var(--bg-card);border:1px solid var(--border);
  border-radius:12px;box-shadow:var(--shadow);min-width:220px;padding:8px;display:none;
  flex-direction:column;gap:2px;z-index:50;max-height:320px;overflow:auto;
}
.team-switch.open .team-dropdown{display:flex;}
.team-option{
  display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 10px;border-radius:8px;
  font-size:13px;font-weight:600;color:var(--text-secondary);background:none;border:none;cursor:pointer;
  text-align:left;width:100%;
}
.team-option:hover{background:var(--bg-card-alt);color:var(--text-primary);}
.team-option.active{background:var(--bg-card-alt);color:var(--text-primary);}
.team-option.active .team-option-name::before{content:"\2713  ";color:var(--accent);}
.team-option-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.team-option-count{font-size:11px;color:var(--text-muted);flex-shrink:0;}
</style>
