# Dynamic Post Grid Pro (DPG)

Dynamic Post Grid Pro is a universal WordPress plugin that dynamically scans post types and taxonomies to generate a modern grid system with search, filters, and pagination.

## Install
- Zip this repo **root folder** and upload it via **WP Admin → Plugins → Add New → Upload Plugin**.
- Activate **Dynamic Post Grid Pro (DPG)**.
- (Recommended) Install and activate **WPGraphQL** (the grid queries posts via `POST /graphql`).

Helper (optional):
- `bash make-zip.sh` → writes `dist/dynamic-post-grid-pro.zip`

## Usage
- Add the shortcode: `[dpg_grid]`
- Optional overrides:
	- `[dpg_grid post_types="post,page" posts_per_page="12"]`

## Admin settings
WP Admin → **Settings → Dynamic Grid**
- Post types (which types to query)
- Taxonomy filter visibility (future UI wiring; stored as config)
- Posts per page

## Development (frontend)
Source lives in `src/` and compiled assets live in `build/` (these are what WordPress enqueues).

Commands:
- `npm install`
- `npm run build`
- `npm run start` (watch mode)

## Features
- Auto-detects post types and taxonomies
- Configurable taxonomy filters
- GraphQL-powered filtering (WPGraphQL)
- Live search (debounced)
- Pagination
- React frontend
- Admin settings for post types, taxonomies, and posts per page
- Performance optimizations (debounced search, optimized queries, lazy loading)
- Security: sanitization and capability checks

## System Architecture
User → React UI → GraphQL → WPGraphQL → WP_Query → Database → Response → UI

## Technical Architecture
- **Backend:**
	- DPG_Plugin (core)
	- DPG_Admin (admin panel)
	- DPG_GraphQL (GraphQL integration)
	- DPG_Query_Builder (query logic)
- **Frontend:**
	- GridContainer
	- SearchBar
	- FiltersPanel
	- Pagination
	- GridItem

## Delivery
- WordPress plugin ZIP
- React build
- GraphQL schema
