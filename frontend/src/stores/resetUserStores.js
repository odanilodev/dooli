import { useCoursesStore } from './courses';
import { useCoursePlayerStore } from './coursePlayer';
import { useCommentsStore } from './comments';
import { useRatingsStore } from './ratings';
import { useProfileStore } from './profile';
import { useNotificationsStore } from './notifications';

/**
 * Clears every user-scoped store so stale data from a previous session is never
 * reused after logout or an expired token (avoids sending the wrong team_id).
 */
export function resetUserStores() {
  useCoursesStore().reset();
  useCoursePlayerStore().reset();
  useCommentsStore().reset();
  useRatingsStore().reset();
  useProfileStore().reset();
  useNotificationsStore().reset();
}
