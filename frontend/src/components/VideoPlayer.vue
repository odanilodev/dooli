<template>
  <div ref="holder" class="video-holder">
    <iframe
      v-if="!isYouTube && embedUrl"
      ref="iframeEl"
      class="video-frame"
      :src="embedUrl"
      title="Player de vídeo"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
      allowfullscreen
    ></iframe>
    <div v-if="errorInfo" class="video-error" role="alert">
      <strong>{{ errorInfo.title }}</strong>
      <p>{{ errorInfo.text }}</p>
      <small v-if="errorInfo.code">Código do erro: {{ errorInfo.code }}</small>
    </div>
  </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  platform: { type: String, default: null },
  embedUrl: { type: String, default: null },
  link: { type: String, default: null },
  duration: { type: Number, default: 0 },
  resumeFrom: { type: Number, default: 0 },
});

const emit = defineEmits(['progress', 'ready', 'error']);

const holder = ref(null);
const iframeEl = ref(null);
const isYouTube = ref(false);
const errorInfo = ref(null);

// Códigos do VdoCipher que indicam navegador/sistema sem suporte ao DRM.
const INCOMPATIBLE_CODES = [2021, 2022, 2023, 2024, 2025];

function reportPlayerError(rawCode) {
  const code = Number(rawCode) || null;

  if (code && INCOMPATIBLE_CODES.includes(code)) {
    errorInfo.value = {
      code,
      title: 'Seu navegador ou sistema não é compatível com este vídeo',
      text: 'Atualize o navegador (Chrome, Edge ou Firefox) para a versão mais recente, tente outro navegador ou use o celular. Em computadores com Windows 7/8 pode ser necessário usar outro dispositivo.',
    };
  } else {
    errorInfo.value = {
      code,
      title: 'Não foi possível reproduzir o vídeo',
      text: 'Recarregue a página ou tente em outro navegador. Se o problema continuar, fale com o suporte informando o código abaixo.',
    };
  }

  emit('error', errorInfo.value.title);
}

let teardown = () => {};
let ytPlayer = null;
let ytInterval = null;

const scriptCache = {};

function loadScript(src) {
  if (scriptCache[src]) {
    return scriptCache[src];
  }

  scriptCache[src] = new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${src}"]`);

    if (existing) {
      if (existing.dataset.loaded === 'true') {
        resolve();
        return;
      }

      existing.addEventListener('load', () => resolve());
      existing.addEventListener('error', () => reject(new Error('script_error')));
      return;
    }

    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => {
      script.dataset.loaded = 'true';
      resolve();
    };
    script.onerror = () => reject(new Error('script_error'));
    document.head.appendChild(script);
  });

  return scriptCache[src];
}

function youtubeId(url) {
  const match = (url || '').match(
    /(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:embed\/|watch\?v=|v\/))([\w-]{6,})/,
  );

  return match ? match[1] : null;
}

function isVimeoUrl(url) {
  return /vimeo\.com/i.test(url || '');
}

function emitProgress(position, duration) {
  emit('progress', {
    position: Number(position) || 0,
    duration: Number(duration) || props.duration || 0,
  });
}

async function bindVdoCipher() {
  await loadScript('https://player.vdocipher.com/v2/api.js');

  const iframe = iframeEl.value;
  const player = window.VdoPlayer.getInstance(iframe);
  const video = player.video;

  const onTime = () => emitProgress(video.currentTime, video.duration);
  const onLoaded = () => {
    if (props.resumeFrom > 0) {
      try {
        video.currentTime = props.resumeFrom;
      } catch (e) {
        /* seek unsupported */
      }
    }

    emit('ready');
  };

  const onError = () => reportPlayerError(video.error?.code ?? video.error);

  video.addEventListener('timeupdate', onTime);
  video.addEventListener('pause', onTime);
  video.addEventListener('ended', onTime);
  video.addEventListener('loadedmetadata', onLoaded);
  video.addEventListener('error', onError);

  teardown = () => {
    try {
      video.removeEventListener('error', onError);
      video.removeEventListener('timeupdate', onTime);
      video.removeEventListener('pause', onTime);
      video.removeEventListener('ended', onTime);
      video.removeEventListener('loadedmetadata', onLoaded);
    } catch (e) {
      /* ignore */
    }
  };
}

async function bindYouTube() {
  const id = youtubeId(props.link || props.embedUrl);

  if (!id) {
    emit('ready');
    return;
  }

  await loadScript('https://www.youtube.com/iframe_api');

  await new Promise((resolve) => {
    const start = () => {
      ytPlayer = new window.YT.Player(holder.value, {
        videoId: id,
        playerVars: { rel: 0, playsinline: 1 },
        events: {
          onReady: () => {
            if (props.resumeFrom > 0) {
              ytPlayer.seekTo(props.resumeFrom, true);
            }

            emit('ready');
            resolve();
          },
          onStateChange: () => emitProgress(ytPlayer?.getCurrentTime?.(), ytPlayer?.getDuration?.()),
        },
      });
    };

    if (window.YT && window.YT.Player) {
      start();
      return;
    }

    const previous = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => {
      if (typeof previous === 'function') {
        previous();
      }

      start();
    };
  });

  ytInterval = setInterval(() => {
    if (ytPlayer?.getCurrentTime) {
      emitProgress(ytPlayer.getCurrentTime(), ytPlayer.getDuration());
    }
  }, 1000);

  teardown = () => {
    clearInterval(ytInterval);
    try {
      ytPlayer?.destroy?.();
    } catch (e) {
      /* ignore */
    }
  };
}

async function bindVimeo() {
  await loadScript('https://player.vimeo.com/api/player.js');

  const player = new window.Vimeo.Player(iframeEl.value);
  const onTime = (data) => emitProgress(data?.seconds, data?.duration);

  player.on('timeupdate', onTime);
  player.ready().then(() => {
    if (props.resumeFrom > 0) {
      player.setCurrentTime(props.resumeFrom).catch(() => {});
    }

    emit('ready');
  }).catch(() => {});

  teardown = () => {
    try {
      player.off('timeupdate', onTime);
      player.destroy?.();
    } catch (e) {
      /* ignore */
    }
  };
}

onMounted(async () => {
  if (!props.embedUrl && !props.link) {
    return;
  }

  try {
    if (props.platform === 'vdocipher') {
      await bindVdoCipher();
      return;
    }

    if (youtubeId(props.link || props.embedUrl)) {
      isYouTube.value = true;
      await nextTick();
      await bindYouTube();
      return;
    }

    if (isVimeoUrl(props.link || props.embedUrl)) {
      await bindVimeo();
      return;
    }

    emit('ready');
  } catch (e) {
    reportPlayerError(null);
  }
});

onBeforeUnmount(() => {
  teardown();
});
</script>

<style scoped>
.video-holder {
  position: absolute;
  inset: 0;
}

.video-error {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 14px 18px;
  background: rgba(15, 15, 20, 0.92);
  color: #fff;
  font-size: 14px;
  line-height: 1.4;
  z-index: 5;
}

.video-error p {
  margin: 4px 0;
}

.video-error small {
  opacity: 0.7;
}

.video-holder :deep(iframe) {
  position: absolute;
  inset: 0;
  width: 100% !important;
  height: 100% !important;
  border: 0;
}
</style>
