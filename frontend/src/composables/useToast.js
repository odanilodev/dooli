import { useToastStore } from '../stores/toasts';

export function useToast() {
  return useToastStore();
}
