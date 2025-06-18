<template>
  <div class="profile-view py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="card p-8">
        <div class="flex items-center mb-8">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mr-4">
            <i class="fas fa-user text-red-600 text-2xl"></i>
          </div>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Mon Profil</h1>
            <p class="text-gray-600">Gérez vos informations et consultez vos réservations</p>
          </div>
        </div>

        <!-- User Info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <h2 class="text-xl font-semibold mb-4 text-gray-900">
              <i class="fas fa-user-circle text-red-600 mr-2"></i>
              Informations personnelles
            </h2>
            <div class="space-y-3">
              <div>
                <label class="block text-sm font-medium text-gray-700">Nom</label>
                <p class="text-gray-900">{{ user.nom }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <p class="text-gray-900">{{ user.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">Téléphone</label>
                <p class="text-gray-900">{{ user.telephone }}</p>
              </div>
            </div>
          </div>

          <div>
            <h2 class="text-xl font-semibold mb-4 text-gray-900">
              <i class="fas fa-star text-yellow-500 mr-2"></i>
              Programme de fidélité
            </h2>
            <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 p-6 rounded-lg border border-yellow-200">
              <div class="text-center">
                <div class="text-3xl font-bold text-yellow-600 mb-2">
                  {{ user.points_fidelite || 0 }}
                </div>
                <p class="text-yellow-800">Points de fidélité</p>
                <p class="text-sm text-yellow-700 mt-2">
                  Gagnez des points à chaque achat et bénéficiez de réductions !
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Reservations -->
        <div>
          <h2 class="text-xl font-semibold mb-6 text-gray-900">
            <i class="fas fa-ticket-alt text-red-600 mr-2"></i>
            Mes Réservations
          </h2>

          <div v-if="loadingReservations" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-red-600 mb-2"></i>
            <p class="text-gray-600">Chargement des réservations...</p>
          </div>

          <div v-else-if="reservations.length === 0" class="text-center py-12 bg-gray-50 rounded-lg">
            <i class="fas fa-calendar-times text-4xl text-gray-400 mb-4"></i>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Aucune réservation</h3>
            <p class="text-gray-500 mb-4">Vous n'avez pas encore effectué de réservation.</p>
            <router-link to="/matches" class="btn-primary">
              <i class="fas fa-search mr-2"></i>
              Découvrir les matchs
            </router-link>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="reservation in reservations"
              :key="reservation.id"
              class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow"
            >
              <div class="flex justify-between items-start mb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">
                    {{ reservation.equipe_domicile }} vs {{ reservation.equipe_exterieur }}
                  </h3>
                  <div class="flex items-center text-gray-600 mt-1">
                    <i class="fas fa-calendar mr-2"></i>
                    <span>{{ formatDate(reservation.date_match) }}</span>
                  </div>
                  <div class="flex items-center text-gray-600 mt-1">
                    <i class="fas fa-map-marker-alt mr-2"></i>
                    <span>{{ reservation.stade_nom }}, {{ reservation.stade_ville }}</span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                        :class="getStatusClass(reservation.statut)">
                    {{ getStatusText(reservation.statut) }}
                  </span>
                </div>
              </div>

              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="font-medium text-gray-700">Catégorie:</span>
                  <p class="text-gray-900">{{ reservation.categorie }}</p>
                </div>
                <div>
                  <span class="font-medium text-gray-700">Billets:</span>
                  <p class="text-gray-900">{{ reservation.nombre_billets }}</p>
                </div>
                <div>
                  <span class="font-medium text-gray-700">Total:</span>
                  <p class="text-gray-900 font-semibold">{{ reservation.prix_total }} DH</p>
                </div>
                <div>
                  <span class="font-medium text-gray-700">Réservé le:</span>
                  <p class="text-gray-900">{{ formatDate(reservation.created_at) }}</p>
                </div>
              </div>

              <div v-if="reservation.statut === 'confirme'" class="mt-4 pt-4 border-t border-gray-200">
                <button class="btn-secondary text-sm">
                  <i class="fas fa-download mr-2"></i>
                  Télécharger les billets PDF
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'ProfileView',
  data() {
    return {
      user: {},
      reservations: [],
      loadingReservations: true,
      error: null
    }
  },
  async created() {
    await this.initializeUserData();
    await this.loadReservations();
  },
  methods: {
    async initializeUserData() {
      try {
        const savedUser = localStorage.getItem('user');
        this.user = savedUser ? JSON.parse(savedUser) : {};
        
        // Vérification de l'authentification
        if (!localStorage.getItem('token')) {
          this.redirectToLogin();
        }
      } catch (e) {
        console.error('Error parsing user data:', e);
        this.redirectToLogin();
      }
    },
    async loadReservations() {
      try {
        const response = await axios.get(
          'http://localhost/Billet/backend/api/reservations.php',
          {
            headers: this.authHeaders()
          }
        );
        this.reservations = response.data || [];
      } catch (error) {
        this.handleReservationError(error);
      } finally {
        this.loadingReservations = false;
      }
    },
    authHeaders() {
      const token = localStorage.getItem('token');
      return {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json'
      };
    },
    handleReservationError(error) {
      if (error.response?.status === 401) {
        this.redirectToLogin();
      } else {
        this.error = 'Erreur lors du chargement des réservations';
        console.error('Reservation error:', error.response?.data || error.message);
      }
    },
    redirectToLogin() {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      this.$router.push('/login?redirect=' + encodeURIComponent(this.$route.fullPath));
    },
    
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    },
    
    getStatusClass(status) {
      switch (status) {
        case 'confirme':
          return 'bg-green-100 text-green-800'
        case 'en_attente':
          return 'bg-yellow-100 text-yellow-800'
        case 'annule':
          return 'bg-red-100 text-red-800'
        default:
          return 'bg-gray-100 text-gray-800'
      }
    },
    
    getStatusText(status) {
      switch (status) {
        case 'confirme':
          return 'Confirmé'
        case 'en_attente':
          return 'En attente'
        case 'annule':
          return 'Annulé'
        default:
          return status
      }
    }
  }
}
</script>