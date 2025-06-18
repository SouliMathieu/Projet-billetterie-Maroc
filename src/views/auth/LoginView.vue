<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center">
          <i class="fas fa-futbol text-red-600 text-4xl gold-accent"></i>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Connexion à votre compte
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Ou
          <router-link to="/register" class="font-medium text-red-600 hover:text-red-500">
            créez un nouveau compte
          </router-link>
        </p>
      </div>
      
      <form class="mt-8 space-y-6" @submit.prevent="login">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">Email</label>
            <input
              id="email"
              name="email"
              type="email"
              v-model="form.email"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-red-500 focus:border-red-500 focus:z-10 sm:text-sm"
              placeholder="Adresse email"
            />
          </div>
          <div>
            <label for="password" class="sr-only">Mot de passe</label>
            <input
              id="password"
              name="password"
              type="password"
              v-model="form.password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-red-500 focus:border-red-500 focus:z-10 sm:text-sm"
              placeholder="Mot de passe"
            />
          </div>
        </div>

        <div v-if="error" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-red-800">
                {{ error }}
              </p>
            </div>
          </div>
        </div>

        <div>
          <button
            type="submit"
            :disabled="loading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
          >
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
              <i class="fas fa-sign-in-alt text-red-500 group-hover:text-red-400"></i>
            </span>
            <span v-if="loading">
              <i class="fas fa-spinner fa-spin mr-2"></i>
              Connexion...
            </span>
            <span v-else>Se connecter</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'LoginView',
  data() {
    return {
      form: {
        email: '',
        password: ''
      },
      loading: false,
      error: null
    }
  },
  methods: {
async login() {
  this.loading = true;
  this.error = null;
  
  try {
    const response = await axios.post(
      'http://localhost/Billet/backend/api/auth/login.php',
      JSON.stringify(this.form), // Convertir explicitement en JSON
      {
        headers: {
          'Content-Type': 'application/json'
        },
        withCredentials: true // Important pour les cookies/sessions
      }
    );
    
    if (response.data.success) {
      localStorage.setItem('auth', JSON.stringify(response.data));
      this.$router.push('/profile');
    } else {
      this.error = response.data.message || "Erreur de connexion";
    }
  } catch (error) {
    console.error('Login error:', error);
    if (error.response) {
      this.error = error.response.data?.message || 
                 error.response.statusText || 
                 "Erreur de serveur";
    } else if (error.request) {
      this.error = "Pas de réponse du serveur - vérifiez votre connexion";
    } else {
      this.error = "Erreur de configuration de la requête";
    }
  } finally {
    this.loading = false;
  }
},
getErrorMessage(error) {
  if (error.message.includes('Token')) return "Erreur d'authentification";
  return error.response?.data?.message || "Échec de la connexion";
},
    handleLoginError(error) {
      const status = error.response?.status;
      this.error = status === 401 
        ? 'Identifiants incorrects'
        : status === 500
        ? 'Erreur serveur - veuillez réessayer plus tard'
        : 'Erreur de connexion';
        
      console.error('Login error:', error.response?.data || error.message);
    }
  }
}
</script>