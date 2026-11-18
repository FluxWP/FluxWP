// Web preview has no expo-updates.
export const otaDeviceCode = (): string => '';
export const otaEnabled = (): boolean => false;
export const startOtaIdentity = (): (() => void) => () => {};
