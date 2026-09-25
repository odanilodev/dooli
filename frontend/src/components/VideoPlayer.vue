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

  video.addEventListener('timeupdate', onTime);
  video.addEventListener('pause', onTime);
  video.addEventListener('ended', onTime);
  video.addEventListener('loadedmetadata', onLoaded);

  teardown = () => {
    try {
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
    emit('error', 'Não foi possível iniciar o player de vídeo.');
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

.video-holder :deep(iframe) {
  position: absolute;
  inset: 0;
  width: 100% !important;
  height: 100% !important;
  border: 0;
}
</style>
