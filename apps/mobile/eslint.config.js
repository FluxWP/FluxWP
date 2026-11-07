// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  expoConfig,
  { ignores: ['dist/*', '.expo/*', 'assets/brand/*', 'scripts/*.cjs'] },
  {
    rules: {
      'no-restricted-imports': ['error', { paths: [{ name: 'react-native-mmkv', message: 'Use src/platform/storage.' }] }],
    },
  },
  { files: ['src/platform/**'], rules: { 'no-restricted-imports': 'off' } },
]);
