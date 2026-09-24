<template>
  <main class="not-found">
    <span class="not-found-code">404</span>
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-muted);"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/><path d="M8 11h6"/></svg>
    <h1 class="not-found-title">Página não encontrada</h1>
    <p class="not-found-text">O link que você acessou não existe ou foi movido. Confira o endereço ou volte para o início.</p>
    <router-link to="/" class="btn btn-primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Voltar para o início
    </router-link>
  </main>
</template>
