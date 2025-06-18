<template>
  <div class="matches-view py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">
          <i class="fas fa-calendar-alt text-red-600 mr-3"></i>
          Matchs à Venir
        </h1>
        <p class="text-xl text-gray-600">
          Découvrez tous les matchs du championnat marocain et réservez vos places
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-4xl text-red-600 mb-4"></i>
        <p class="text-gray-600">Chargement des matchs...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md mx-auto">
          <i class="fas fa-exclamation-triangle text-red-600 text-3xl mb-4"></i>
          <p class="text-red-800">{{ error }}</p>
          <button @click="loadMatches" class="mt-4 btn-primary">
            <i class="fas fa-redo mr-2"></i>
            Réessayer
          </button>
        </div>
      </div>

      <!-- Matches Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div
          v-for="match in matches"
          :key="match.id"
          class="match-card"
        >
          <!-- Match Header -->
          <div class="p-6 bg-gradient-to-r from-red-600 to-green-600 text-white">
            <div class="flex justify-between items-center mb-4">
              <div class="text-sm font-medium">
                {{ match.date_formatted }}
              </div>
              <div class="text-sm font-medium">
                {{ match.heure_formatted }}
              </div>
            </div>
            
            <!-- Teams -->
            <div class="flex items-center justify-between">
              <div class="text-center flex-1">
                <img :src="match.logo_domicile" :alt="match.equipe_domicile" class="w-12 h-12 mx-auto mb-2 rounded-full bg-white p-1">
                <p class="font-semibold text-sm">{{ match.equipe_domicile }}</p>
              </div>
              
              <div class="px-4">
                <span class="text-2xl font-bold gold-accent">VS</span>
              </div>
              
              <div class="text-center flex-1">
                <img :src="match.logo_exterieur" :alt="match.equipe_exterieur" class="w-12 h-12 mx-auto mb-2 rounded-full bg-white p-1">
                <p class="font-semibold text-sm">{{ match.equipe_exterieur }}</p>
              </div>
            </div>
          </div>

          <!-- Stadium Info -->
          <div class="p-4 bg-gray-50 border-b">
            <div class="flex items-center text-gray-700">
              <i class="fas fa-map-marker-alt mr-2"></i>
              <span class="font-medium">{{ match.stade_nom }}</span>
              <span class="mx-2">•</span>
              <span>{{ match.stade_ville }}</span>
            </div>
          </div>

          <!-- Pricing -->
          <div class="p-6">
            <div class="space-y-3 mb-6">
              <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                <div>
                  <span class="font-semibold text-yellow-800">VIP</span>
                  <span class="text-sm text-yellow-600 block">{{ match.places_disponibles.vip }} places</span>
                </div>
                <span class="font-bold text-yellow-800">{{ match.prix_dynamique_vip }} DH</span>
              </div>
              
              <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg border border-blue-200">
                <div>
                  <span class="font-semibold text-blue-800">Normale</span>
                  <span class="text-sm text-blue-600 block">{{ match.places_disponibles.normale }} places</span>
                </div>
                <span class="font-bold text-blue-800">{{ match.prix_dynamique_normale }} DH</span>
              </div>
              
              <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg border border-green-200">
                <div>
                  <span class="font-semibold text-green-800">Tribune</span>
                  <span class="text-sm text-green-600 block">{{ match.places_disponibles.tribune }} places</span>
                </div>
                <span class="font-bold text-green-800">{{ match.prix_dynamique_tribune }} DH</span>
              </div>
            </div>

            <!-- Proximity Warning -->
            <div v-if="match.jours_restants <= 7" class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
              <p class="text-sm text-orange-800">
                <i class="fas fa-clock mr-1"></i>
                Plus que {{ match.jours_restants }} jour(s) - Prix majoré !
              </p>
            </div>

            <!-- Reserve Button -->
            <router-link
              :to="{ name: 'reservation', params: { matchId: match.id } }"
              class="btn-primary w-full text-center block"
            >
              <i class="fas fa-ticket-alt mr-2"></i>
              Réserver mes places
            </router-link>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && !error && matches.length === 0" class="text-center py-12">
        <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
        <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun match programmé</h3>
        <p class="text-gray-500">Revenez bientôt pour découvrir les prochains matchs !</p>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'FootballMatchesView',
  data() {
    return {
      matches: [],
      loading: true,
      error: null
    }
  },
  mounted() {
    this.loadMatches()
  },
  methods: {
    async loadMatches() {
  this.loading = true
  this.error = null
  
  try {
    const response = await axios.get('http://localhost/Billet/backend/api/matches.php')
    console.log('Response:', response.data) // Pour débugger
    
    if (Array.isArray(response.data)) {
      this.matches = response.data
    } else if (response.data.data) {
      this.matches = response.data.data
    } else {
      this.matches = []
    }
  } catch (error) {
    console.error('Erreur complète:', error)
    if (error.response) {
      this.error = `Erreur serveur: ${error.response.status}`
    } else if (error.request) {
      this.error = 'Impossible de contacter le serveur'
    } else {
      this.error = 'Erreur lors du chargement des matchs'
    }
  } finally {
    this.loading = false
  }
}
  }
}
</script>