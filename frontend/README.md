# Delta Class (Vue 3 + Vite)

Versão SPA do template Delta Class — mesma identidade visual da versão HTML estática, agora navegando sem recarregar a página (Vue Router em modo hash).

## Rodando localmente

```bash
npm install
npm run dev
```

Abre em `http://localhost:5173`.

## Build de produção

```bash
npm run build
```

Gera os arquivos finais em `dist/` — é só subir essa pasta em qualquer hospedagem estática (ou servir via um backend, como uma rota "catch-all" de um framework PHP/Node). Como o roteamento usa modo **hash** (`#/cursos`, `#/aula`, etc.), não é preciso configurar rewrite no servidor.

```bash
npm run preview
```

Serve o build de produção localmente para conferir antes de publicar.

## Estrutura

```
src/
  assets/style.css        estilos globais (design tokens, componentes, responsivo)
  composables/            estado compartilhado: tema, sidebar mobile, busca do header
  data/                   dados dos cursos (fonte única usada no Dashboard e no Catálogo)
  components/             Sidebar, Header e os cards de curso reutilizáveis
  layouts/                casca da área logada (AppLayout) e das telas de auth (AuthLayout)
  views/                  uma por rota: Dashboard, Cursos, Aula, Perfil, Configurações, Login, Cadastro
  router/                 definição das rotas
```

## Rotas

| Caminho            | Tela                          |
|---------------------|-------------------------------|
| `#/`                | Dashboard (Início)            |
| `#/cursos`          | Catálogo de cursos (filtro + busca) |
| `#/aula`            | Player de aula                |
| `#/perfil`          | Perfil do aluno                |
| `#/configuracoes`   | Configurações da conta         |
| `#/login`           | Login                          |
| `#/cadastro`        | Criar conta                    |

## O que já é funcional

- Tema claro/escuro com persistência (`localStorage`), compartilhado entre todas as telas.
- Menu mobile (off-canvas) com overlay.
- Catálogo com filtro por status e busca em tempo real (ignora acentos).
- Abas, acordeão de módulos e "marcar como concluída" na aula.
- Formulários de login/cadastro/perfil/configurações com validação simples (sem backend — é só trocar os `submit`/`save` em cada `view` por chamadas reais à sua API).
- Trocar de tema nas Configurações reflete no toggle do header e vice-versa (estado compartilhado via composable).

## Próximos passos sugeridos

- Trocar os dados mockados de `src/data/courses.js` por uma chamada à sua API.
- Trocar as funções `submit`/`save` dos formulários por chamadas reais (fetch/axios).
- Adicionar um guard de rota (`router.beforeEach`) quando existir autenticação de verdade.
