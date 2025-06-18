import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './style.css'
import axios from 'axios';

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')

// Intercepteur pour ajouter automatiquement le token
axios.interceptors.request.use(config => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Gestion des erreurs 401 (token invalide/expiré)
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login'; // Redirection forcée
    }
    return Promise.reject(error);
  }
);