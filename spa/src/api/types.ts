export interface Game {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon_url: string | null;
  status: string;
  has_servers: boolean;
  metadata: Record<string, unknown> | null;
}

export interface ServerAllocation {
  id: number;
  external_id: number | null;
  ip: string;
  ip_alias: string | null;
  port: number;
  is_default: boolean;
  notes: string | null;
}

export interface Server {
  id: number;
  game_id: number;
  // Only present when the backend eager-loaded the owning Game (e.g.
  // ServerController::show) — see ServerCardWidget, which needs it to
  // build a /games/{slug}/servers/{id} link without a second fetch.
  game_slug?: string | null;
  server_group_id: number | null;
  name: string;
  slug: string;
  description: string | null;
  status: string;
  max_players: number | null;
  current_players: number | null;
  cpu_current: number | null;
  cpu_limit: number | null;
  cpu_percent: number | null;
  memory_current: number | null;
  memory_limit: number | null;
  memory_percent: number | null;
  disk_current: number | null;
  disk_limit: number | null;
  disk_percent: number | null;
  network_rx: number | null;
  network_tx: number | null;
  node_name: string | null;
  supported_features: string[] | null;
  game_version: string | null;
  last_polled_at: string | null;
  allocations: ServerAllocation[];
}

export interface WebPage {
  id: number;
  title: string;
  slug: string;
  game_id: number | null;
  status: string;
  content: string | null;
  path: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  avatar: string | null;
  bio: string | null;
  preferences: Record<string, unknown> | null;
  is_admin: boolean;
}

export interface DashboardWidget {
  id: number;
  dashboard_page_id: number;
  widget_type: string;
  config: Record<string, unknown> | null;
  order: number;
  position_x: number;
  position_y: number;
  width: number;
  height: number;
}

export interface DashboardPage {
  id: number;
  title: string;
  order: number;
  widgets: DashboardWidget[];
}

export type PageLayoutSubjectType = 'server' | 'game' | 'home' | 'games-list';

export interface PageLayoutWidget {
  id: number;
  page_layout_id: number;
  widget_type: string;
  config: Record<string, unknown> | null;
  position_x: number;
  position_y: number;
  width: number;
  height: number;
}

export interface PageLayout {
  id: number;
  subject_type: PageLayoutSubjectType;
  subject_id: number;
  widgets: PageLayoutWidget[];
}

export interface AssetTag {
  id: number;
  name: string;
  slug: string;
}

export interface Asset {
  id: number;
  owner_type: string | null;
  owner_id: number | null;
  folder_id: number | null;
  tags: AssetTag[];
  url: string;
  thumbnail_url: string;
  mime_type: string;
  size: number;
  width: number | null;
  height: number | null;
  alt_text: string | null;
  uploaded_by: number | null;
  created_at: string;
}

export interface AssetListMeta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface AssetList {
  items: Asset[];
  meta: AssetListMeta;
}

export type AssetFolderVisibility = 'public' | 'admin_only' | 'user_private';

export interface AssetFolder {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  visibility: AssetFolderVisibility;
  owner_id: number | null;
  path: string;
  created_at: string;
}

export type ThemeTokens = Record<string, string>;

export interface ApiEnvelope<T> {
  data: T;
}
