<template>
  <main class="content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Banners</h1>
        <p class="page-subtitle">Controlam o destaque da home da área do aluno.</p>
      </div>
    </div>

    <BannerManager
      placement="home"
      empty-title="Nenhum banner da home"
      empty-description="Cadastre o primeiro destaque da home."
      form-description="A imagem ocupa o destaque da home. Os textos abaixo compõem o hero."
    />
  </main>
</template>

<script setup>
import BannerManager from '../components/admin/BannerManager.vue';
</script>
