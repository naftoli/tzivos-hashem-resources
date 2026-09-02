/** PHP lives in the parent /resources/ folder, not under site/dist. */
export function phpUrl(file: string): string {
  return `/resources/${file.replace(/^\//, '')}`;
}
