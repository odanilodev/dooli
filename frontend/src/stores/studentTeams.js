import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../api/http';

const STORAGE_KEY = 'delta-class-team';

function readStored() {
  const value = Number(localStorage.getItem(STORAGE_KEY));

  return Number.isInteger(value) && value > 0 ? value : null;
}

function persist(teamId) {
  if (teamId) {
    localStorage.setItem(STORAGE_KEY, String(teamId));
  } else {
    localStorage.removeItem(STORAGE_KEY);
  }
}

export const useStudentTeamsStore = defineStore('studentTeams', () => {
  const teams = ref([]);
  const selectedTeamId = ref(null);
  const loading = ref(false);
  const loaded = ref(false);

  let inflight = null;

  function fetchTeams(force = false) {
    if (loaded.value && !force) {
      return Promise.resolve(teams.value);
    }

    if (inflight) {
      return inflight;
    }

    inflight = (async () => {
      loading.value = true;

      try {
        const { data } = await api.get('/me/teams');
        teams.value = data.data ?? [];

        const stored = readStored();
        const valid = teams.value.some((team) => team.id === stored);

        selectedTeamId.value = valid ? stored : (teams.value[0]?.id ?? null);
        persist(selectedTeamId.value);
        loaded.value = true;

        return teams.value;
      } catch (e) {
        teams.value = [];
        selectedTeamId.value = null;

        return [];
      } finally {
        loading.value = false;
        inflight = null;
      }
    })();

    return inflight;
  }

  function selectTeam(teamId) {
    if (teamId === selectedTeamId.value) {
      return;
    }

    selectedTeamId.value = teamId;
    persist(teamId);
  }

  function reset() {
    teams.value = [];
    selectedTeamId.value = null;
    loading.value = false;
    loaded.value = false;
    persist(null);
  }

  return {
    teams,
    selectedTeamId,
    loading,
    loaded,
    fetchTeams,
    selectTeam,
    reset,
  };
});
