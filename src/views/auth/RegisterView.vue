<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-12 flex items-center justify-center">
          <i class="fas fa-futbol text-red-600 text-4xl gold-accent"></i>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Créer votre compte
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Ou
          <router-link to="/login" class="font-medium text-red-600 hover:text-red-500">
            connectez-vous à votre compte existant
          </router-link>
        </p>
      </div>
      
      <form class="mt-8 space-y-6" @submit.prevent="register">
        <div class="space-y-4">
          <div>
            <label for="nom" class="block text-sm font-medium text-gray-700">Nom complet</label>
            <input
              id="nom"
              name="nom"
              type="text"
              v-model="form.nom"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
              placeholder="Votre nom complet"
            />
          </div>
          
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input
              id="email"
              name="email"
              type="email"
              v-model="form.email"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
              placeholder="votre@email.com"
            />
          </div>
          
          <div>
            <label for="telephone" class="block text-sm font-medium text-gray-700">Téléphone</label>
            <input
              id="telephone"
              name="telephone"
              type="tel"
              v-model="form.telephone"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
              placeholder="+212 6XX XXX XXX"
            />
          </div>
          
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe</label>
            <input
              id="password"
              name="password"
              type="password"
              v-model="form.password"
              required
              minlength="6"
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500 sm:text-sm"
              placeholder="Minimum 6 caractères"
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

        <div v-if="success" class="rounded-md bg-green-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <i class="fas fa-check-circle text-green-400"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-green-800">
                {{ success }}
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
              <i class="fas fa-user-plus text-red-500 group-hover:text-red-400"></i>
            </span>
            <span v-if="loading">
              <i class="fas fa-spinner fa-spin mr-2"></i>
              Création...
            </span>
            <span v-else>Créer mon compte</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'RegisterView',
  data() {
    return {
      form: {
        nom: '',
        email: '',
        telephone: '',
        password: ''
      },
      loading: false,
      error: null,
      success: null
    }
  },
  methods: {
    async register() {
      this.loading = true
      this.error = null
      this.success = null
      
      try {
        const response = await axios.post('http://localhost/Billet/backend/api/auth/register.php', this.form)
        
        this.success = 'Compte créé avec succès! Redirection...'
        
        localStorage.setItem('token', response.data.token)
        localStorage.setItem('user', JSON.stringify(response.data.user))
        
        setTimeout(() => {
          this.$router.push('/matches')
        }, 2000)
      } catch (error) {
        this.error = error.response?.data?.message || 'Erreur lors de la création du compte'
      } finally {
        this.loading = false
      }
    }
  }
}
</script>