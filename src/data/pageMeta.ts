import type { BranchId } from '@/types';
import meta from '@/data/static/pageMeta.json';

/**
 * Small, always-bundled page metadata (see scripts/genPageMeta.mjs). This is the
 * part of the old `pageRegistry` that App/ContentPage need *synchronously* — a
 * page's branch, whether it exists, and its altcrumb branch — split out from the
 * ~4.3 MB of page HTML so routing resolves without pulling in every page body.
 * The bodies themselves load on demand via `loadPageHtml` (src/data/pageBodies.ts).
 */
interface PageMeta {
  branch: Record<string, string>;
  altCrumb: Record<string, string>;
}

const META = meta as PageMeta;

/** pageId -> the branch its content bundle lives under (home/date/campaign/…). */
export const pageBranch = META.branch;

/**
 * pageId -> the `data-branch` of its `<template class="altcrumb">`, for the ~121
 * date-branch "issue" pages also reachable from Campaign. Ported from the old
 * `pageRegistry`'s one-time regex pass, now precomputed at build time so it stays
 * available without loading the (lazy) date bundle.
 */
export const altCrumbBranch = META.altCrumb;

/** Whether `id` is a known content page (i.e. renderable by ContentPage). */
export function hasPage(id: string): boolean {
  return Object.prototype.hasOwnProperty.call(pageBranch, id);
}

/** A page's branch as a typed `BranchId`, falling back to 'home'. */
export function branchOfPage(id: string): BranchId {
  return (pageBranch[id] ?? 'home') as BranchId;
}
