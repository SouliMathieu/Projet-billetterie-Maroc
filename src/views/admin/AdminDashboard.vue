<template>
  <div class="admin-dashboard py-8">
    <!-- Modal d'authentification -->
    <div v-if="!isAuthenticated" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
        <div class="text-center mb-6">
          <i class="fas fa-shield-alt text-4xl text-red-600 mb-4"></i>
          <h2 class="text-2xl font-bold text-gray-900">Accès Administrateur</h2>
        </div>
        <form @submit.prevent="authenticate" class="space-y-4">
          <input 
            type="password" 
            v-model="adminToken" 
            class="w-full px-3 py-2 border rounded-md focus:ring-2 focus:ring-red-500"
            placeholder="Token admin"
            required
          >
          <div v-if="authError" class="text-red-600 text-sm">{{ authError }}</div>
          <button type="submit" class="w-full btn-primary" :disabled="authenticating">
            {{ authenticating ? 'Vérification...' : 'Se connecter' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Dashboard principal -->
    <div v-if="isAuthenticated" class="max-w-7xl mx-auto px-4">
      <!-- Header -->
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-tachometer-alt text-red-600 mr-3"></i>
            Dashboard Admin
          </h1>
        </div>
        <button @click="logout" class="text-red-600 hover:text-red-800">
          <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
        </button>
      </div>

      <!-- Analytics Cards -->
      <div v-if="analytics" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-euro-sign text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Ventes du mois</p>
              <p class="text-2xl font-bold">{{ analytics.monthly_sales }} DH</p>
              <p class="text-sm" :class="analytics.sales_growth >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ analytics.sales_growth }}% ce mois
              </p>
            </div>
          </div>
        </div>
        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-chart-line text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Taux remplissage</p>
              <p class="text-2xl font-bold">{{ analytics.avg_occupancy }}%</p>
            </div>
          </div>
        </div>
        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fas fa-trophy text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Match populaire</p>
              <p class="text-sm font-medium">{{ analytics.popular_match?.teams }}</p>
              <p class="text-xs text-gray-500">{{ analytics.popular_match?.tickets }} billets</p>
            </div>
          </div>
        </div>
        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm text-gray-600">Total utilisateurs</p>
              <p class="text-2xl font-bold">{{ users?.length || 0 }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <nav class="flex space-x-8">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            :class="activeTab === tab.key ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500'"
            class="py-2 px-1 border-b-2 font-medium text-sm">
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Content par onglet -->
      <div class="card">
        <!-- Équipes -->
        <div v-if="activeTab === 'equipes'" class="p-6">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Gestion des Équipes</h2>
            <button @click="showModal('equipe')" class="btn-primary">
              <i class="fas fa-plus mr-2"></i>Ajouter
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ville</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fondation</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="equipe in equipes" :key="equipe.id">
                  <td class="px-6 py-4 whitespace-nowrap font-medium">{{ equipe.nom }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ equipe.ville }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ equipe.fondation_annee }}</td>
                  <td class="px-6 py-4 whitespace-nowrap space-x-2">
                    <button @click="editItem('equipe', equipe)" class="text-blue-600 hover:text-blue-900">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button @click="deleteItem('equipes', equipe.id)" class="text-red-600 hover:text-red-900">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Stades -->
        <div v-if="activeTab === 'stades'" class="p-6">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold">Gestion des Stades</h2>
            <button @click="showModal('stade')" class="btn-primary">
              <i class="fas fa-plus mr-2"></i>Ajouter
            </button>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ville</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacité</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="stade in stades" :key="stade.id">
                  <td class="px-6 py-4 whitespace-nowrap font-medium">{{ stade.nom }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ stade.ville }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ stade.capacite_totale }}</td>
                  <td class="px-6 py-4 whitespace-nowrap space-x-2">
                    <button @click="editItem('stade', stade)" class="text-blue-600 hover:text-blue-900">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button @click="deleteItem('stades', stade.id)" class="text-red-600 hover:text-red-900">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Réservations -->
        <div v-if="activeTab === 'reservations'" class="p-6">
          <h2 class="text-xl font-semibold mb-6">Gestion des Réservations</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Match</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Billets</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="reservation in reservations" :key="reservation.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium">{{ reservation.nom_client }}</div>
                    <div class="text-sm text-gray-500">{{ reservation.email_client }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium">{{ reservation.equipe_domicile }} vs {{ reservation.equipe_exterieur }}</div>
                    <div class="text-sm text-gray-500">{{ formatDate(reservation.date_match) }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ reservation.nombre_billets }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ reservation.prix_total }} DH</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="getStatusClass(reservation.statut)" class="px-2 py-1 text-xs rounded-full">
                      {{ reservation.statut }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap space-x-2">
                    <button v-if="reservation.statut === 'en_attente'" 
                      @click="updateReservation(reservation.id, 'confirm')" 
                      class="text-green-600 hover:text-green-900">
                      <i class="fas fa-check"></i>
                    </button>
                    <button v-if="reservation.statut !== 'annule'" 
                      @click="updateReservation(reservation.id, 'cancel')" 
                      class="text-red-600 hover:text-red-900">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Utilisateurs -->
        <div v-if="activeTab === 'users'" class="p-6">
          <h2 class="text-xl font-semibold mb-6">Gestion des Utilisateurs</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Points</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réservations</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dépenses</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="user in users" :key="user.id">
                  <td class="px-6 py-4 whitespace-nowrap font-medium">{{ user.nom }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ user.email }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ user.points_fidelite }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ user.total_reservations }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">{{ user.total_depense }} DH</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <button @click="editUserPoints(user)" class="text-blue-600 hover:text-blue-900">
                      <i class="fas fa-coins"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Modal générique -->
      <div v-if="showModalForm" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-8 max-w-md w-full mx-4">
          <h3 class="text-lg font-bold mb-4">{{ editingItem ? 'Modifier' : 'Ajouter' }} {{ modalType }}</h3>
          
          <form @submit.prevent="saveItem" class="space-y-4">
            <!-- Champs pour équipe -->
            <div v-if="modalType === 'equipe'">
              <input v-model="formData.nom" placeholder="Nom de l'équipe" class="form-input" required>
              <input v-model="formData.ville" placeholder="Ville" class="form-input" required>
              <input v-model="formData.fondation_annee" type="number" placeholder="Année de fondation" class="form-input">
              <textarea v-model="formData.description" placeholder="Description" class="form-input"></textarea>
            </div>

            <!-- Champs pour stade -->
            <div v-if="modalType === 'stade'">
              <input v-model="formData.nom" placeholder="Nom du stade" class="form-input" required>
              <input v-model="formData.ville" placeholder="Ville" class="form-input" required>
              <input v-model="formData.capacite_totale" type="number" placeholder="Capacité totale" class="form-input" required>
              <input v-model="formData.adresse" placeholder="Adresse" class="form-input">
            </div>

            <div class="flex justify-end space-x-3">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                Annuler
              </button>
              <button type="submit" class="btn-primary">
                {{ editingItem ? 'Modifier' : 'Ajouter' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AdminDashboard',
  data() {
    return {
      isAuthenticated: false,
      adminToken: '',
      authError: '',
      authenticating: false,
      activeTab: 'equipes',
      
      // Data
      analytics: null,
      equipes: [],
      stades: [],
      reservations: [],
      users: [],
      
      // Modal
      showModalForm: false,
      modalType: '',
      editingItem: null,
      formData: {},
      
      tabs: [
        { key: 'equipes', label: 'Équipes' },
        { key: 'stades', label: 'Stades' },
        { key: 'reservations', label: 'Réservations' },
        { key: 'users', label: 'Utilisateurs' }
      ]
    }
  },
  
  mounted() {
    const savedToken = localStorage.getItem('admin_token')
    if (savedToken === 'admin123') {
      this.isAuthenticated = true
      this.adminToken = savedToken
      this.loadAllData()
    }
  },
  
  methods: {
    async authenticate() {
      this.authenticating = true
      this.authError = ''
      
      try {
        await this.apiCall('GET', 'admin-analytics')
        this.isAuthenticated = true
        localStorage.setItem('admin_token', this.adminToken)
        this.loadAllData()
      } catch (error) {
        this.authError = error.response?.status === 401 ? 'Token invalide' : 'Erreur de connexion'
      } finally {
        this.authenticating = false
      }
    },
    
    logout() {
      this.isAuthenticated = false
      this.adminToken = ''
      localStorage.removeItem('admin_token')
      this.resetData()
    },
    
    resetData() {
      this.analytics = null
      this.equipes = []
      this.stades = []
      this.reservations = []
      this.users = []
    },
    
    async loadAllData() {
      try {
        const [analytics, equipes, stades, reservations, users] = await Promise.all([
          this.apiCall('GET', 'admin-analytics'),
          this.apiCall('GET', 'admin-equipes'),
          this.apiCall('GET', 'admin-stades'),
          this.apiCall('GET', 'admin-reservations'),
          this.apiCall('GET', 'admin-users')
        ])
        
        this.analytics = analytics.data
        this.equipes = equipes.data
        this.stades = stades.data
        this.reservations = reservations.data
        this.users = users.data
      } catch (error) {
        console.error('Erreur de chargement:', error)
        if (error.response?.status === 401) this.logout()
      }
    },
    
    async apiCall(method, endpoint, data = null) {
      const token = this.adminToken || localStorage.getItem('admin_token')
      const config = {
        method,
        url: `http://localhost/Billet/backend/api/${endpoint}.php`,
        headers: { 'Admin-Token': token },
        data
      }
      return axios(config)
    },
    
    showModal(type) {
      this.modalType = type
      this.showModalForm = true
      this.formData = {}
      this.editingItem = null
    },
    
    editItem(type, item) {
      this.modalType = type
      this.showModalForm = true
      this.editingItem = item
      this.formData = { ...item }
    },
    
    closeModal() {
      this.showModalForm = false
      this.editingItem = null
      this.formData = {}
    },
    
    async saveItem() {
      try {
        const endpoint = this.modalType === 'equipe' ? 'admin-equipes' : 'admin-stades'
        const method = this.editingItem ? 'PUT' : 'POST'
        
        if (this.editingItem) {
          this.formData.id = this.editingItem.id
        }
        
        await this.apiCall(method, endpoint, this.formData)
        this.closeModal()
        this.loadAllData()
      } catch (error) {
        alert('Erreur lors de la sauvegarde')
      }
    },
    
    async deleteItem(endpoint, id) {
      if (confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
        try {
          await this.apiCall('DELETE', `admin-${endpoint}`, { id })
          this.loadAllData()
        } catch (error) {
          alert('Erreur lors de la suppression')
        }
      }
    },
    
    async updateReservation(id, action) {
      try {
        await this.apiCall('PUT', 'admin-reservations', { id, action })
        this.loadAllData()
      } catch (error) {
        alert('Erreur lors de la mise à jour')
      }
    },
    
    editUserPoints(user) {
      const newPoints = prompt(`Points actuels: ${user.points_fidelite}. Nouveaux points:`, user.points_fidelite)
      if (newPoints !== null) {
        this.updateUserPoints(user.id, parseInt(newPoints))
      }
    },
    
    async updateUserPoints(id, points) {
      try {
        await this.apiCall('PUT', 'admin-users', { id, points_fidelite: points })
        this.loadAllData()
      } catch (error) {
        alert('Erreur lors de la mise à jour des points')
      }
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
      const classes = {
        'en_attente': 'bg-yellow-100 text-yellow-800',
        'confirme': 'bg-green-100 text-green-800',
        'annule': 'bg-red-100 text-red-800'
      }
      return classes[status] || 'bg-gray-100 text-gray-800'
    }
  }
}
</script>

<style scoped>
.card {
  @apply bg-white rounded-lg shadow-sm border border-gray-200;
}

.btn-primary {
  @apply bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors;
}

.btn-primary:disabled {
  @apply bg-red-400 cursor-not-allowed;
}

.form-input {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 mb-3;
}
</style>