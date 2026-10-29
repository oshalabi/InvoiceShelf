<template>
  <div class="flex flex-col items-center justify-center h-screen">
<<<<<<< HEAD
    <div class="loader loader-white">
      <div class="loader-spined">
        <div class="loader--icon">
          <svg
            class="offset-45deg text-primary-500"
            width="27"
            height="27"
            viewBox="0 0 27 27"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M25.9053 1.75122C25.8037 1.44653 25.5498 1.19263 25.2451 1.09106C23.6201 0.735596 22.3506 0.735596 21.0811 0.735596C15.8506 0.735596 12.7021 3.57935 10.3662 7.2356L5.03418 7.2356C4.22168 7.28638 3.25684 7.84497 2.85059 8.60669L0.362305 13.634C0.311523 13.7864 0.260742 13.9895 0.260742 14.1418C0.260742 14.8528 0.768555 15.3606 1.47949 15.3606H6.70996L5.59277 16.5286C4.9834 17.0872 4.93262 18.1536 5.59277 18.8137L8.18262 21.4036C8.74121 21.9622 9.80762 22.0637 10.4678 21.4036L11.585 20.2864V25.5168C11.6357 26.2278 12.1436 26.7356 12.8545 26.7356C13.0068 26.7356 13.21 26.6848 13.3623 26.634L18.3896 24.1458C19.1514 23.7395 19.71 22.7747 19.71 21.9622V16.6301C23.417 14.2942 26.21 11.1458 26.21 5.91528C26.2607 4.64575 26.2607 3.37622 25.9053 1.75122ZM19.7607 9.26685C18.5928 9.26685 17.7295 8.40356 17.7295 7.2356C17.7295 6.11841 18.5928 5.20435 19.7607 5.20435C20.8779 5.20435 21.792 6.11841 21.792 7.2356C21.792 8.40356 20.8779 9.26685 19.7607 9.26685Z"
              fill="currentColor"
            />
          </svg>
        </div>
      </div>

      <div class="pufs text-primary-500">
        <i class="text-primary-500"></i><i></i><i></i> <i></i><i></i><i></i>
        <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i>
      </div>
      <div class="particles text-primary-500">
        <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i> <i></i
        ><i></i><i></i> <i></i><i></i><i></i> <i></i><i></i><i></i>
      </div>
      <MainLogo
        class="
          absolute
          block
          h-auto
          max-w-full
          transform
          -translate-x-1/2 -translate-y-1/2
          w-28
          text-primary-400
          top-1/2
          left-1/2
        "
        alt="ExactBalance Logo"
      />
=======
    <MainLogo
      class="w-auto h-10 mb-6 text-heading"
      alt="InvoiceShelf Logo"
    />
    <div class="flex space-x-1.5">
      <span class="loader-dot" />
      <span class="loader-dot delay-150" />
      <span class="loader-dot delay-300" />
>>>>>>> c8d10f76891c4efdecb71c04b49109e8f67f8e7c
    </div>
  </div>
</template>

<script setup lang="ts">
import MainLogo from '@/scripts/components/icons/MainLogo.vue'

interface Props {
  showBgOverlay?: boolean
}

withDefaults(defineProps<Props>(), {
  showBgOverlay: false,
})
</script>

<style>
.loader-dot {
  width: 0.625rem;
  height: 0.625rem;
  border-radius: 9999px;
  background-color: oklch(0.585 0.233 277.117);
  animation: loader-bounce 1.4s infinite ease-in-out both;
}

.loader-dot.delay-150 {
  animation-delay: 0.15s;
}

.loader-dot.delay-300 {
  animation-delay: 0.3s;
}

@keyframes loader-bounce {
  0%, 80%, 100% {
    transform: scale(0.4);
    opacity: 0.4;
  }
  40% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
