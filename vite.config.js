import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  
  // Configuration du serveur de développement
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, '/backend/api')
      }
    }
  },

  // Configuration des alias pour les imports
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
      '@assets': resolve(__dirname, 'src/assets'),
      '@components': resolve(__dirname, 'src/components'),
      '@views': resolve(__dirname, 'src/views')
    }
  },

  // Configuration du dossier public pour les assets statiques
  publicDir: 'public',

  // Configuration des assets
  assetsInclude: [
    '**/*.png', 
    '**/*.jpg', 
    '**/*.jpeg', 
    '**/*.gif', 
    '**/*.svg', 
    '**/*.webp',
    '**/*.ico'
  ],

  // Configuration du build
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    
    // Optimisation des assets
    rollupOptions: {
      output: {
        // Organisation des fichiers de sortie
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name.split('.')
          const extType = info[info.length - 1]
          
          // Organisation par type de fichier
          if (/\.(png|jpe?g|svg|gif|tiff|bmp|ico)$/i.test(assetInfo.name)) {
            return `images/[name]-[hash][extname]`
          }
          if (/\.(woff2?|eot|ttf|otf)$/i.test(assetInfo.name)) {
            return `fonts/[name]-[hash][extname]`
          }
          if (/\.(css)$/i.test(assetInfo.name)) {
            return `css/[name]-[hash][extname]`
          }
          
          return `assets/[name]-[hash][extname]`
        },
        
        chunkFileNames: 'js/[name]-[hash].js',
        entryFileNames: 'js/[name]-[hash].js'
      }
    },

    // Limite pour l'inlining des assets (en bytes)
    assetsInlineLimit: 4096,

    // Optimisation des images
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true
      }
    }
  },

  // Variables d'environnement
  define: {
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_DEVTOOLS__: false
  },

  // Configuration CSS
  css: {
    preprocessorOptions: {
      scss: {
        additionalData: `@import "@/styles/variables.scss";`
      }
    },
    devSourcemap: true
  },

  // Optimisation des dépendances
  optimizeDeps: {
    include: [
      'vue',
      'vue-router',
      'axios'
    ],
    exclude: []
  }
})
