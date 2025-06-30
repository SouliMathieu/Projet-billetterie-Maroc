<template>
  <div class="reservation-view py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Back Button -->
      <div class="mb-6">
        <router-link to="/matches" class="text-red-600 hover:text-red-800 font-medium">
          <i class="fas fa-arrow-left mr-2"></i>
          Retour aux matchs
        </router-link>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-4xl text-red-600 mb-4"></i>
        <p class="text-gray-600">Chargement...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="text-center py-12">
        <div class="bg-red-50 border border-red-200 rounded-lg p-6 max-w-md mx-auto">
          <i class="fas fa-exclamation-triangle text-red-600 text-3xl mb-4"></i>
          <p class="text-red-800">{{ error }}</p>
        </div>
      </div>

      <!-- Reservation Form -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Match Info -->
        <div class="card p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-900">
            <i class="fas fa-info-circle text-red-600 mr-2"></i>
            Informations du Match
          </h2>
          
          <div class="space-y-4">
            <!-- Teams -->
            <div class="teams-container">
              <div class="team-section">
                <img 
                  :src="getTeamLogo(match.logo_domicile)" 
                  :alt="`Logo ${match.equipe_domicile}`" 
                  class="team-logo-reservation"
                  @error="handleImageError"
                />
                <p class="font-semibold">{{ match.equipe_domicile }}</p>
              </div>
              <div class="vs-section-reservation">
                <span class="vs-text-reservation">VS</span>
              </div>
              <div class="team-section">
                <img 
                  :src="getTeamLogo(match.logo_exterieur)" 
                  :alt="`Logo ${match.equipe_exterieur}`" 
                  class="team-logo-reservation"
                  @error="handleImageError"
                />
                <p class="font-semibold">{{ match.equipe_exterieur }}</p>
              </div>
            </div>

            <!-- Date & Stadium -->
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-gray-50 p-4 rounded-lg">
                <i class="fas fa-calendar text-red-600 mb-2"></i>
                <p class="font-semibold">{{ match.date_formatted }}</p>
                <p class="text-sm text-gray-600">{{ match.heure_formatted }}</p>
              </div>
              <div class="bg-gray-50 p-4 rounded-lg">
                <i class="fas fa-map-marker-alt text-red-600 mb-2"></i>
                <p class="font-semibold">{{ match.stade_nom }}</p>
                <p class="text-sm text-gray-600">{{ match.stade_ville }}</p>
              </div>
            </div>

            <!-- Pricing -->
            <div class="space-y-2">
              <h3 class="font-semibold text-gray-900 mb-3">Tarifs</h3>
              <div class="grid grid-cols-1 gap-2">
                <div class="flex justify-between p-2 bg-yellow-50 rounded">
                  <span>VIP ({{ match.places_disponibles.vip }} places)</span>
                  <span class="font-bold">{{ match.prix_dynamique_vip }} DH</span>
                </div>
                <div class="flex justify-between p-2 bg-blue-50 rounded">
                  <span>Normale ({{ match.places_disponibles.normale }} places)</span>
                  <span class="font-bold">{{ match.prix_dynamique_normale }} DH</span>
                </div>
                <div class="flex justify-between p-2 bg-green-50 rounded">
                  <span>Tribune ({{ match.places_disponibles.tribune }} places)</span>
                  <span class="font-bold">{{ match.prix_dynamique_tribune }} DH</span>
                </div>
              </div>
            </div>

            <!-- City Info -->
            <div v-if="cityInfo" class="bg-blue-50 p-4 rounded-lg">
              <h3 class="font-semibold text-blue-800 mb-2">
                <i class="fas fa-info-circle mr-2"></i>
                Infos Pratiques - {{ match.stade_ville }}
              </h3>
              <div class="text-sm text-blue-700 space-y-2">
                <div v-if="cityInfo.transport_info">
                  <strong><i class="fas fa-bus mr-1"></i> Transport:</strong> 
                  <p class="mt-1">{{ cityInfo.transport_info }}</p>
                </div>
                <div v-if="cityInfo.activites_touristiques">
                  <strong><i class="fas fa-camera mr-1"></i> À visiter:</strong> 
                  <p class="mt-1">{{ cityInfo.activites_touristiques }}</p>
                </div>
                <div v-if="cityInfo.restaurants">
                  <strong><i class="fas fa-utensils mr-1"></i> Restaurants:</strong> 
                  <p class="mt-1">{{ cityInfo.restaurants }}</p>
                </div>
                <div v-if="cityInfo.hotels">
                  <strong><i class="fas fa-bed mr-1"></i> Hébergement:</strong> 
                  <p class="mt-1">{{ cityInfo.hotels }}</p>
                </div>
                <div v-if="cityInfo.climat_description">
                  <strong><i class="fas fa-cloud-sun mr-1"></i> Climat:</strong> 
                  <p class="mt-1">{{ cityInfo.climat_description }}</p>
                </div>
                <div v-if="cityInfo.liens_utiles">
                  <strong><i class="fas fa-link mr-1"></i> Liens utiles:</strong> 
                  <p class="mt-1">{{ cityInfo.liens_utiles }}</p>
                </div>
              </div>
            </div>

            <!-- Loading City Info -->
            <div v-else-if="loadingCityInfo" class="bg-blue-50 p-4 rounded-lg">
              <div class="flex items-center text-blue-700">
                <i class="fas fa-spinner fa-spin mr-2"></i>
                <span>Chargement des informations de {{ match.stade_ville }}...</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Reservation Form -->
        <div class="card p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-900">
            <i class="fas fa-ticket-alt text-red-600 mr-2"></i>
            Réservation
          </h2>

          <form @submit.prevent="submitReservation" class="space-y-6">
            <!-- Ticket Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Catégorie de place
              </label>
              <select
                v-model="form.categorie"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
              >
                <option value="">Sélectionnez une catégorie</option>
                <option value="VIP" :disabled="match.places_disponibles.vip === 0">
                  VIP - {{ match.prix_dynamique_vip }} DH
                  {{ match.places_disponibles.vip === 0 ? ' (Complet)' : '' }}
                </option>
                <option value="Normale" :disabled="match.places_disponibles.normale === 0">
                  Normale - {{ match.prix_dynamique_normale }} DH
                  {{ match.places_disponibles.normale === 0 ? ' (Complet)' : '' }}
                </option>
                <option value="Tribune" :disabled="match.places_disponibles.tribune === 0">
                  Tribune - {{ match.prix_dynamique_tribune }} DH
                  {{ match.places_disponibles.tribune === 0 ? ' (Complet)' : '' }}
                </option>
              </select>
            </div>

            <!-- Number of Tickets -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre de billets
              </label>
              <select
                v-model="form.nombre_billets"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
              >
                <option value="">Nombre de billets</option>
                <option v-for="n in availableTickets" :key="n" :value="n">
                  {{ n }} billet{{ n > 1 ? 's' : '' }}
                </option>
              </select>
            </div>

            <!-- Customer Info -->
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nom complet
                </label>
                <input
                  type="text"
                  v-model="form.nom_client"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                  placeholder="Votre nom complet"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Email
                </label>
                <input
                  type="email"
                  v-model="form.email_client"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                  placeholder="votre@email.com"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Téléphone
                </label>
                <input
                  type="tel"
                  v-model="form.telephone_client"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500"
                  placeholder="+212 6XX XXX XXX"
                />
              </div>
            </div>

            <!-- Total Price -->
            <div v-if="totalPrice > 0" class="bg-gray-50 p-4 rounded-lg border">
              <div class="flex justify-between items-center">
                <span class="font-semibold text-gray-900">Total à payer:</span>
                <span class="text-2xl font-bold text-red-600">{{ totalPrice }} DH</span>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="formError" class="bg-red-50 border border-red-200 rounded-lg p-4">
              <p class="text-red-800">{{ formError }}</p>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="submitting"
              class="w-full btn-primary text-lg py-3"
            >
              <span v-if="submitting">
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Traitement...
              </span>
              <span v-else">
                <i class="fas fa-credit-card mr-2"></i>
                Procéder au paiement
              </span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'ReservationFormView',
  props: ['matchId'],
  data() {
    return {
      match: null,
      cityInfo: null,
      loadingCityInfo: false,
      loading: true,
      error: null,
      form: {
        categorie: '',
        nombre_billets: '',
        nom_client: '',
        email_client: '',
        telephone_client: ''
      },
      formError: null,
      submitting: false
    }
  },
  computed: {
    availableTickets() {
      if (!this.form.categorie || !this.match) return []
      
      let max = 0
      switch (this.form.categorie) {
        case 'VIP':
          max = Math.min(this.match.places_disponibles.vip, 6)
          break
        case 'Normale':
          max = Math.min(this.match.places_disponibles.normale, 6)
          break
        case 'Tribune':
          max = Math.min(this.match.places_disponibles.tribune, 6)
          break
      }
      
      return Array.from({ length: max }, (_, i) => i + 1)
    },
    totalPrice() {
      if (!this.form.categorie || !this.form.nombre_billets || !this.match) return 0
      
      let prix = 0
      switch (this.form.categorie) {
        case 'VIP':
          prix = this.match.prix_dynamique_vip
          break
        case 'Normale':
          prix = this.match.prix_dynamique_normale
          break
        case 'Tribune':
          prix = this.match.prix_dynamique_tribune
          break
      }
      
      return prix * this.form.nombre_billets
    }
  },
  watch: {
    'form.categorie'() {
      this.form.nombre_billets = ''
    }
  },
  mounted() {
    this.loadMatch()
    this.prefillUserInfo()
  },
  methods: {
    async loadMatch() {
      try {
        const token = localStorage.getItem('token')
        if (!token) {
          this.$router.push('/login')
          return
        }

        const response = await axios.get(`http://localhost/Billet/backend/api/matches.php`, {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        })
        
        if (!response.data || !Array.isArray(response.data)) {
          throw new Error('Invalid response format')
        }
        
        this.match = response.data.find(m => m.id == this.matchId)
        
        if (!this.match) {
          this.error = 'Match non trouvé'
        } else {
          // Debug pour vérifier les logos
          console.log('Match trouvé:', {
            id: this.match.id,
            domicile: this.match.equipe_domicile,
            exterieur: this.match.equipe_exterieur,
            logo_domicile: this.match.logo_domicile,
            logo_exterieur: this.match.logo_exterieur
          })
          await this.loadCityInfo()
        }
      } catch (error) {
        if (error.response && error.response.status === 401) {
          localStorage.removeItem('token')
          localStorage.removeItem('user')
          this.$router.push('/login')
        } else {
          this.error = 'Erreur lors du chargement du match'
          console.error('Error loading match:', error)
        }
      } finally {
        this.loading = false
      }
    },

    async loadCityInfo() {
      if (!this.match || !this.match.stade_ville) return
      
      this.loadingCityInfo = true
      try {
        const response = await axios.get(`http://localhost/Billet/backend/api/ville-infos.php?ville=${encodeURIComponent(this.match.stade_ville)}`)
        this.cityInfo = response.data
      } catch (error) {
        console.error('Erreur lors du chargement des infos ville:', error)
        this.cityInfo = null
      } finally {
        this.loadingCityInfo = false
      }
    },
    
    prefillUserInfo() {
      try {
        const userString = localStorage.getItem('user')
        if (userString) {
          const user = JSON.parse(userString)
          if (user && typeof user === 'object') {
            this.form.nom_client = user.nom || ''
            this.form.email_client = user.email || ''
            this.form.telephone_client = user.telephone || ''
          }
        }
      } catch (e) {
        console.error('Error parsing user data:', e)
        localStorage.removeItem('user')
      }
    },

    getTeamLogo(logoUrl) {
      console.log('Logo URL reçue:', logoUrl) // Debug
      
      if (!logoUrl) {
        return '/default-team-logo.png'
      }
      
      if (logoUrl.startsWith('http')) {
        return logoUrl
      }
      
      const finalUrl = logoUrl.startsWith('/') ? logoUrl : '/' + logoUrl
      console.log('URL finale:', finalUrl) // Debug
      
      return finalUrl
    },

    handleImageError(event) {
      console.log('Erreur de chargement d\'image:', event.target.src)
      
      // Éviter la boucle infinie
      if (event.target.src.includes('default-team-logo.png')) {
        // Si même l'image par défaut échoue, utiliser une image SVG inline
        event.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMzIiIGN5PSIzMiIgcj0iMzIiIGZpbGw9IiNGM0Y0RjYiLz4KPHN2ZyB4PSIxNiIgeT0iMTYiIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM2QjcyODAiIHN0cm9rZS13aWR0aD0iMiI+CjxwYXRoIGQ9Im0xNSAxOC0zLTMtMy0zIDMtMyAzIDMiLz4KPC9zdmc+Cjwvc3ZnPgo='
        event.target.style.backgroundColor = '#f3f4f6'
        event.target.style.border = '1px solid #e5e7eb'
      } else {
        event.target.src = '/default-team-logo.png'
      }
    },
    
    async submitReservation() {
      this.submitting = true;
      this.formError = null;

      try {
        if (!this.validateForm()) {
          return;
        }

        const token = localStorage.getItem('token');
        if (!token) {
          this.$router.push('/login');
          return;
        }

        console.log('Données envoyées:', {
          match_id: this.matchId,
          ...this.form
        });

        const response = await axios.post('http://localhost/Billet/backend/api/reservations.php', {
          match_id: this.matchId,
          ...this.form
        }, {
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
          }
        });

        console.log('Réponse du serveur:', response.data);

        if (response.data.reservation_id) {
          this.$router.push(`/payment/${response.data.reservation_id}`);
        } else {
          throw new Error('Réponse inattendue du serveur');
        }
      } catch (error) {
        console.error('Erreur complète:', error);
        
        if (error.response) {
          console.error('Détails erreur:', error.response.data);
          this.formError = error.response.data.message || 'Erreur lors de la réservation';
          
          if (error.response.status === 401) {
            localStorage.removeItem('token');
            this.$router.push('/login');
          }
        } else {
          this.formError = error.message || 'Erreur de connexion au serveur';
        }
      } finally {
        this.submitting = false;
      }
    },

    validateForm() {
      if (!this.form.categorie) {
        this.formError = 'Veuillez sélectionner une catégorie';
        return false;
      }
      
      if (!this.form.nombre_billets) {
        this.formError = 'Veuillez sélectionner le nombre de billets';
        return false;
      }
      
      if (!this.form.nom_client || !this.form.email_client || !this.form.telephone_client) {
        this.formError = 'Veuillez remplir tous les champs obligatoires';
        return false;
      }
      
      if (!this.matchId) {
        this.formError = 'Identifiant de match manquant';
        return false;
      }
      
      return true;
    }
  }
}
</script>
