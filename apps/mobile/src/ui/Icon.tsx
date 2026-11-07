// Server icon names (common set, dashicons-*, menu slug) → lucide, same table as the desktop panel
// (packages/ui/src/Icon.tsx). URLs (http/data:) draw as images.
import { createElement } from 'react';
import { Image } from 'expo-image';
import {
  Archive, Award, BarChart3, Bell, BookOpen, Briefcase, Calendar, Camera, Check, CircleCheck, CircleDot, ClipboardCheck,
  ClipboardList, Clock, Cloud, Code, CreditCard, Database, Download, Eye, FileText, Film, Folder, FormInput, Gauge, Gift, Globe,
  Heart, HelpCircle, House, Image as ImageIcon, Inbox, Info, KeyRound, Languages, LayoutDashboard, LayoutGrid, Link, ListChecks, Lock,
  Mail, MapPin, Megaphone, MessageCircle, MessageSquare, Monitor, Music, Package, Paintbrush, Palette, Phone, PieChart,
  Plug, Plus, QrCode, Receipt, RefreshCw, Search, Send, Settings, Share2, Shield, ShieldCheck, ShoppingBag, ShoppingCart,
  Smartphone, Sparkles, Star, Store, Tag, Tags, Trash2, TreePine, TrendingUp, Truck, Tv, Unlock, Upload, User, UserCog,
  Users, Video, Wallet, Wrench, X, TriangleAlert, Gamepad2, Clapperboard, Menu, ChevronRight, ChevronLeft, ChevronDown, ArrowLeft, EllipsisVertical, RotateCw, ExternalLink, WifiOff, LogOut, Sun, Moon, SunMoon, Pencil, StickyNote, Filter, Box, Bot, Copy, CircleAlert, type LucideIcon,
} from 'lucide-react-native';

const map: Record<string, LucideIcon> = {
  // genel / modül adları
  dashboard: LayoutDashboard, home: House, heart: Heart, chat: MessageCircle, whatsapp: MessageCircle, task: ClipboardCheck,
  tasks: ClipboardCheck, check: Check, email: Mail, mail: Mail, inbox: Inbox, award: Award, certificate: Award, post: FileText,
  page: FileText, media: ImageIcon, image: ImageIcon, chart: BarChart3, settings: Settings, users: Users, user: User, comment: MessageSquare,
  cart: ShoppingCart, clock: Clock, update: RefreshCw, performance: Gauge, translation: Languages, share: Share2, ai: Sparkles,
  bell: Bell, forms: FormInput, form: FormInput, studio: Clapperboard, video: Video, seo: Search, qr: QrCode, tv: Tv,
  remote: Gamepad2, backup: Archive, roles: UserCog, analytics: TrendingUp, donation: Heart, tree: TreePine, phone: Phone,
  // dashicons
  'admin-home': House, 'admin-site': Globe, 'admin-site-alt': Globe, 'admin-site-alt2': Globe, 'admin-site-alt3': Globe,
  'admin-post': FileText, 'admin-page': FileText, 'admin-media': ImageIcon, 'admin-links': Link, 'admin-comments': MessageSquare,
  'admin-appearance': Palette, 'admin-plugins': Plug, 'admin-users': Users, 'admin-tools': Wrench, 'admin-settings': Settings,
  'admin-generic': Settings, 'admin-network': Globe, 'admin-multisite': Globe, 'admin-customizer': Paintbrush,
  'admin-collapse': LayoutGrid, 'admin-network-alt': Globe,
  'format-chat': MessageCircle, 'format-image': ImageIcon, 'format-gallery': ImageIcon, 'format-video': Video, 'format-audio': Music,
  'format-status': MessageSquare, 'format-aside': FileText, 'format-quote': MessageSquare,
  'email-alt': Mail, 'email-alt2': Mail, awards: Award, 'yes-alt': CircleCheck, yes: Check, 'no-alt': X, no: X,
  'chart-area': BarChart3, 'chart-bar': BarChart3, 'chart-line': TrendingUp, 'chart-pie': PieChart,
  products: Package, store: Store, money: Wallet, 'money-alt': Wallet, calendar: Calendar, 'calendar-alt': Calendar,
  groups: Users, location: MapPin, 'location-alt': MapPin, megaphone: Megaphone, smartphone: Smartphone, tablet: Smartphone,
  desktop: Monitor, laptop: Monitor, warning: TriangleAlert, lock: Lock, unlock: Unlock, shield: Shield, 'shield-alt': ShieldCheck,
  upload: Upload, download: Download, visibility: Eye, 'update-alt': RefreshCw, 'database': Database,
  'cloud': Cloud, 'cloud-saved': Cloud, 'cloud-upload': Upload, palmtree: TreePine, 'plus-alt': Plus, 'plus-alt2': Plus,
  'arrow-right-alt2': Send, 'book': BookOpen, 'book-alt': BookOpen, 'portfolio': Briefcase, 'businessman': User,
  'businesswoman': User, 'businessperson': User, 'id': User, 'id-alt': User, 'camera': Camera, 'camera-alt': Camera,
  'images-alt': ImageIcon, 'images-alt2': ImageIcon, 'video-alt': Video, 'video-alt2': Video, 'video-alt3': Film, 'playlist-video': Film,
  'tag': Tag, 'tickets': Tag, 'tickets-alt': Tag, 'category': Folder, 'archive': Archive, 'feedback': ListChecks,
  'clipboard': ClipboardList, 'list-view': ListChecks, 'editor-code': Code, 'html': Code, 'code-standards': Code,
  'translation-alt': Languages, 'info': Info, 'info-outline': Info, 'editor-help': HelpCircle, 'sos': HelpCircle,
  'phone-alt': Phone, 'cart-alt': ShoppingCart, 'bank': Wallet, 'star-filled': Star, 'star-empty': Star, 'heart-alt': Heart,
  'trash': Trash2, 'search': Search, 'share-alt': Share2, 'share-alt2': Share2, 'networking': Share2, 'rss': Megaphone,
  'performance-alt': Gauge, 'superhero': Shield, 'superhero-alt': Shield, 'privacy': ShieldCheck, 'buddicons-groups': Users,
  'admin-comments-alt': MessageSquare, 'testimonial': MessageSquare, 'welcome-learn-more': BookOpen, 'welcome-write-blog': FileText,
  'welcome-widgets-menus': LayoutGrid, 'screenoptions': LayoutGrid, 'grid-view': LayoutGrid, 'excerpt-view': FileText,
  'media-document': FileText, 'media-default': FileText, 'media-text': FileText, 'media-spreadsheet': FileText,
  'media-archive': Archive, 'media-video': Video, 'media-audio': Music, 'media-code': Code, 'pdf': FileText,
  'gift': Gift, 'car': Truck, 'airplane': Send, 'food': Store, 'coffee': Store, 'beer': Store, 'carrot': TreePine,
  'qrcode': QrCode, 'receipt': Receipt, 'credit-card': CreditCard, 'key': KeyRound, 'filter': Tags,
};

/** WordPress / FluxWP menü slug'ları: ikonu "none" (CSS ile çizilen) menüler için. */
const slugs: [RegExp, LucideIcon][] = [
  [/^index\.php/, LayoutDashboard], [/^edit\.php\?post_type=page/, FileText], [/^edit\.php\?post_type=product/, Package],
  [/^edit\.php/, FileText], [/^upload\.php/, ImageIcon], [/^edit-comments/, MessageSquare], [/^themes\.php/, Palette],
  [/^plugins\.php/, Plug], [/^users\.php|^profile\.php/, Users], [/^tools\.php/, Wrench], [/^options-general/, Settings],
  [/^woocommerce$/, ShoppingBag], [/^wc-admin.*analytics/, BarChart3], [/^woocommerce-marketing/, Megaphone],
  [/^flxb|flx-donate/, Heart], [/^flxs$|flx-certificates/, Award], [/^flx-chat/, MessageCircle], [/^flx-tasks/, ClipboardCheck],
  [/^flxi-posta/, Inbox], [/^flxi$|flx-mail/, Send], [/^emsfb|flx-forms/i, FormInput], [/^flx-analytics/, TrendingUp],
  [/^flx-speed/, Gauge], [/^flx-social/, Share2], [/^flx-ai/, Sparkles], [/^flx-studio/, Clapperboard], [/^flxseo|flx-seo/, Search],
  [/^flx-qr/, QrCode], [/^flx-tv-remote/, Gamepad2], [/^flx-tv-board/, Tv], [/^flxapp|flx-app/, Smartphone],
  [/^flx-(setup|updater)/, RefreshCw], [/^flx-backup/, Archive], [/^flx-lang/, Languages], [/^flx-roles|^flxr/, UserCog], [/^flx-admin|^flxa/, Settings],
];


// Plan §7.8 common names not covered above.
Object.assign(map, { menu: Menu, edit: Pencil, note: StickyNote, trash: Trash2, plus: Plus, x: X, search: Search, filter: Filter, star: Star, globe: Globe, wallet: Wallet, box: Box, plugin: Plug, more: EllipsisVertical, back: ArrowLeft, chevron: ChevronRight, 'chevron-left': ChevronLeft, 'chevron-down': ChevronDown, refresh: RotateCw, external: ExternalLink, offline: WifiOff, logout: LogOut, sun: Sun, moon: Moon, system: SunMoon, bot: Bot, copy: Copy, alert: CircleAlert, key: KeyRound });

export function iconFor(name?: string, slug?: string): LucideIcon {
  const key = (name ?? '').trim().replace(/^dashicons-/, '');
  return map[key] ?? (slug ? slugs.find(([re]) => re.test(slug))?.[1] : undefined) ?? CircleDot;
}

export function Icon({ name, slug, size = 20, color, strokeWidth = 1.8 }: { name?: string; slug?: string; size?: number; color: string; strokeWidth?: number }) {
  const raw = (name ?? '').trim();
  if (/^(https:|data:image\/)/.test(raw)) return <Image source={{ uri: raw }} style={{ width: size, height: size }} contentFit="contain" />;
  return createElement(iconFor(raw, slug), { size, color, strokeWidth });
}
