import { createContext, useContext, useEffect, useMemo, type ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { usePathname } from 'expo-router';
import { useStore } from 'zustand';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import { AiSession, useAccount, useApi, useManifest, useRenderer } from '@fluxwp/runtime';
import { bindAiConfigurationRefresh } from '@fluxwp/runtime/src/modules/ai-configuration';
import { Icon } from '@/ui/Icon';
import { Text } from '@/ui/primitives';
const Context=createContext<AiSession|null>(null);
export function useMobileAi(){const engine=useContext(Context);if(!engine)throw new Error('AI session provider missing');return engine;}
/** Survives navigation within the account; leaving the account aborts local readers. */
export function MobileAiProvider({children}:{children:ReactNode}){
 const {theme}=useUnistyles();const account=useAccount();const api=useApi();const renderer=useRenderer();const path=usePathname();const enabled=!!useManifest().data?.modules.ai;
 const engine=useMemo(()=>new AiSession(api,()=>({url:account.siteUrl,title:account.siteName,app:'FluxWP',platform:'mobile'})),[api,account.id]);
 const s=useStore(engine.store);useEffect(()=>{if(enabled)void engine.initialize();const off=enabled?bindAiConfigurationRefresh(account.id,engine):()=>{};return()=>{off();engine.dispose();};},[engine,enabled,account.id]);
 const onChat=/\/(?:n\/(?:flxai-chat|flx-ai)|m\/ai\/chat)$/.test(path);
 return <Context.Provider value={engine}><View style={styles.frame}>{children}{enabled&&!onChat&&!s.denied&&s.boot?.showAdmin!==false&&<Pressable accessibilityRole="button" accessibilityLabel={renderer.t('ai.title')} onPress={()=>renderer.nav.push({kind:'native',component:'flxai-chat',params:{}})} style={styles.launcher}><Icon name="ai" size={24} color={theme.colors.primary}/>{(s.busy||!!s.chat?.pending.length)&&<Text variant="caption">{renderer.t(s.busy?'ai.working':'ai.approval')}</Text>}</Pressable>}</View></Context.Provider>;
}
const styles=StyleSheet.create(theme=>({frame:{flex:1},launcher:{position:'absolute',right:16,bottom:88,minWidth:54,minHeight:54,maxWidth:170,padding:12,gap:4,alignItems:'center',justifyContent:'center',backgroundColor:theme.colors.primarySoft,borderWidth:1,borderColor:theme.colors.border,borderRadius:24}}));
