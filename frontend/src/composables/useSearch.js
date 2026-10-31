import { ref } from 'vue';

// Estado compartilhado do campo de busca do header. O campo só aparece nas
// rotas com meta.headerSearch e é consumido pelo catálogo de cursos e pela loja.
const query = ref('');

export function useSearch() {
  return { query };
}
