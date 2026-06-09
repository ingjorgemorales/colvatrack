import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.colvatel.colvatrack',
  appName: 'ColvaTrack',
  webDir: 'public/build',
  server: {
    androidScheme: 'https',
  },
};

export default config;
