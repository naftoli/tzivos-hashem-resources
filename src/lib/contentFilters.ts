/**
 * Pure predicates for the `.hktog`/`.hkcard` tag-filter used on a few campaign
 * pages (see the `.hktog` branch of the legacy click-delegation chain, and its
 * wiring in `ContentPage`'s `handleClick`). Split out because it's the one
 * piece of that chain that's an actual reusable predicate rather than a
 * one-line DOM toggle.
 */

/** Should a `.hkcard` whose `data-hk` is a space-separated tag list be hidden given the active tag `v`? */
export function hkCardShouldHide(dataHk: string | null, v: string): boolean {
  if (v === '*') return false;
  const tags = (dataHk || '').split(' ');
  return tags.indexOf(v) < 0;
}
