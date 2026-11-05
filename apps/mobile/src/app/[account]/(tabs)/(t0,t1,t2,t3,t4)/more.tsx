import { useLayoutEffect } from 'react';
import { useNavigation } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { MoreScreen } from '@/shell/MoreScreen';

export default function MoreRoute() {
  const { t } = useTranslation();
  const nav = useNavigation();
  useLayoutEffect(() => nav.setOptions({ title: t('mobile.shell.more') }), [nav, t]);
  return <MoreScreen />;
}
