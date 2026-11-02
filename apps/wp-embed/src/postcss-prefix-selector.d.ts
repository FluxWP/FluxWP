declare module 'postcss-prefix-selector' {
  const prefixer: (opts: { prefix: string; ignoreFiles?: (string | RegExp)[]; transform?: (prefix: string, selector: string, prefixed: string) => string }) => import('postcss').AcceptedPlugin;
  export default prefixer;
}
