import { ref } from 'vue';

// Estado compartilhado do campo de busca do header — usado pelo catálogo de
// cursos para filtrar em tempo real; nas demais páginas é apenas visual.
const query = ref('');

export function useSearch() {
  return { query };
}
