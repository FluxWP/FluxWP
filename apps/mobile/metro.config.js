// Expo SDK 52+ configures monorepos itself (watchFolders = workspace root, hoisted node_modules).
// This only checks that the shared packages are reachable, so a broken install fails loudly.
const { getDefaultConfig } = require('expo/metro-config');
const path = require('node:path');

const config = getDefaultConfig(__dirname);
const root = path.resolve(__dirname, '../..');
if (!config.watchFolders.some((f) => path.resolve(f) === root)) config.watchFolders.push(root);
for (const p of ['@fluxwp/core', '@fluxwp/tokens', '@fluxwp/manifest', '@fluxwp/runtime']) require.resolve(`${p}/package.json`);
if (!config.resolver.assetExts.includes('woff2')) config.resolver.assetExts.push('woff2');

module.exports = config;
