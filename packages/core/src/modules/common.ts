export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  pages: number;
}
