import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';
import { useTenant } from './useTenant';

export function useAccountMenu(variant = 'app') {
  const auth = useAuthStore();
  const { publicRoute } = useTenant();

  const items = computed(() => {
    const entries = [
      {
        key: 'profile',
        label: 'Meu perfil',
        icon: 'profile',
        to: { name: 'perfil' },
      },
    ];

    if (variant === 'app') {
      entries.push({
        key: 'settings',
        label: 'Configurações',
        icon: 'settings',
        to: { name: 'configuracoes' },
      });
    } else {
      entries.push({
        key: 'courses',
        label: 'Meus cursos',
        icon: 'courses',
        to: { name: 'dashboard' },
      });
    }

    entries.push({
      key: 'purchases',
      label: 'Minhas compras',
      icon: 'orders',
      to: publicRoute('public-compras'),
      studentOnly: true,
    });

    return entries.filter((item) => !item.studentOnly || auth.isStudent);
  });

  return { items };
}
