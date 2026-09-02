export type BranchId =
  | 'home'
  | 'date'
  | 'campaign'
  | 'rally'
  | 'promotions'
  | 'marking'
  | 'chitas'
  | 'calendar';

export const KNOWN_BRANCHES: BranchId[] = [
  'home',
  'date',
  'campaign',
  'rally',
  'promotions',
  'marking',
  'chitas',
  'calendar',
];

/** id -> raw HTML fragment for one `<section class="page" data-page="ID">` from the legacy page. */
export type PageBundle = Record<string, string>;

export interface TzhUser {
  success: boolean;
  /** Display name, already prefixed with title where checkAuth.php determined one belongs. */
  name?: string;
  /** Human-readable role label, e.g. "Base Commander", "Teacher", "Headquarters". */
  role?: string;
  /** 1-2 letter avatar initials. */
  initials?: string;
  [key: string]: unknown;
}
