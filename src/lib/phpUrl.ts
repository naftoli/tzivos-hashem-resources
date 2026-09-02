/** Same-origin PHP next to the built app (`/resources/checkAuth.php`, etc.). */
export function phpUrl(file: string): string {
  const base = import.meta.env.BASE_URL.endsWith('/') ? import.meta.env.BASE_URL : `${import.meta.env.BASE_URL}/`;
  return `${base}${file.replace(/^\//, '')}`;
}
