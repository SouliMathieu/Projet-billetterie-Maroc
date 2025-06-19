<template>
  <div class="admin-dashboard py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">
          <i class="fas fa-tachometer-alt text-red-600 mr-3"></i>
          Tableau de Bord Administrateur
        </h1>
        <p class="text-gray-600 mt-2">Gérez les matchs, équipes et réservations</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-calendar text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Matchs Programmés</p>
              <p class="text-2xl font-semibold text-gray-900">{{ stats.total_matches }}</p>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fas fa-ticket-alt text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Billets Vendus</p>
              <p class="text-2xl font-semibold text-gray-900">{{ stats.total_tickets }}</p>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
              <i class="fas fa-euro-sign text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Revenus Total</p>
              <p class="text-2xl font-semibold text-gray-900">{{ stats.total_revenue }} DH</p>
            </div>
          </div>
        </div>

        <div class="card p-6">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fas fa-users text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-600">Utilisateurs</p>
              <p class="text-2xl font-semibold text-gray-900">{{ stats.total_users }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <nav class="flex space-x-8">
          <button
            @click="activeTab = 'matches'"
            :class="activeTab === 'matches' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
          >
            Gestion des Matchs
          </button>
          <button
            @click="activeTab = 'reservations'"
            :class="activeTab === 'reservations' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
          >
            Réservations
          </button>
          <button
            @click="activeTab = 'analytics'"
            :class="activeTab === 'analytics' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
            class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
          >
            Analytics
          </button>
        </nav>
      </div>

      <!-- Matches Tab -->
      <div v-if="activeTab === 'matches'" class="space-y-6">
        <!-- Add Match Button -->
        <div class="flex justify-between items-center">
          <h2 class="text-xl font-semibold text-gray-900">Gestion des Matchs</h2>
          <button @click="showAddMatchModal = true" class="btn-primary">
            <i class="fas fa-plus mr-2"></i>
            Ajouter un Match
          </button>
        </div>

        <!-- Matches Table -->
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Match</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stade</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Billets Vendus</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenus</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="match in matches" :key="match.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900">
                      {{ match.equipe_domicile }} vs {{ match.equipe_exterieur }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ formatDate(match.date_match) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ match.stade_nom }}, {{ match.stade_ville }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ match.billets_vendus }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ match.revenus_total }} DH
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="getStatusClass(match.statut)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                      {{ getStatusText(match.statut) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                    <button @click="editMatch(match)" class="text-indigo-600 hover:text-indigo-900">
                      <i class="fas fa-edit"></i>
                    </button>
                    <button @click="deleteMatch(match.id)" class="text-red-600 hover:text-red-900">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Add/Edit Match Modal -->
      <div v-if="showAddMatchModal || editingMatch" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
          <h3 class="text-lg font-bold text-gray-900 mb-4">
            {{ editingMatch ? 'Modifier le Match' : 'Ajouter un Match' }}
          </h3>
          
          <form @submit.prevent="saveMatch" class="space-y-4">
            <!-- Form fields would go here -->
            <div class="flex justify-end space-x-3">
              <button type="button" @click="closeModal" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                Annuler
              </button>
              <button type="submit" class="btn-primary">
                {{ editingMatch ? 'Modifier' : 'Ajouter' }}
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
      activeTab: 'matches',
      matches: [],
      stats: {
        total_matches: 0,
        total_tickets: 0,
        total_revenue: 0,
        total_users: 0
      },
      showAddMatchModal: false,
      editingMatch: null,
      loading: true
    }
  },
  mounted() {
    this.loadData()
  },
  methods: {
    async loadData() {
      try {
        const response = await axios.get('http://localhost/Billet/backend/api/admin-matches.php', {
  headers: { 
  'Authorization': `Bearer ${localStorage.getItem('token')}`
}
})
        
        this.matches = response.data
        this.calculateStats()
      } catch (error) {
        console.error('Erreur lors du chargement des données:', error)
      } finally {
        this.loading = false
      }
    },
    
    calculateStats() {
      this.stats.total_matches = this.matches.length
      this.stats.total_tickets = this.matches.reduce((sum, match) => sum + parseInt(match.billets_vendus), 0)
      this.stats.total_revenue = this.matches.reduce((sum, match) => sum + parseFloat(match.revenus_total), 0)
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
        case 'programme': return 'bg-blue-100 text-blue-800'
        case 'en_cours': return 'bg-yellow-100 text-yellow-800'
        case 'termine': return 'bg-green-100 text-green-800'
        case 'annule': return 'bg-red-100 text-red-800'
        default: return 'bg-gray-100 text-gray-800'
      }
    },
    
    getStatusText(status) {
      switch (status) {
        case 'programme': return 'Programmé'
        case 'en_cours': return 'En cours'
        case 'termine': return 'Terminé'
        case 'annule': return 'Annulé'
        default: return status
      }
    },
    
    editMatch(match) {
      this.editingMatch = { ...match }
    },
    
    async deleteMatch(matchId) {
      if (confirm('Êtes-vous sûr de vouloir supprimer ce match ?')) {
        try {
          await axios.delete('http://localhost/Billet/backend/api/admin-matches.php', {
            data: { id: matchId },
            headers: { 'Admin-Token': 'admin123' }
          })
          this.loadData()
        } catch (error) {
          alert('Erreur lors de la suppression')
        }
      }
    },
    
    closeModal() {
      this.showAddMatchModal = false
      this.editingMatch = null
    },
    
    async saveMatch() {
      // Implementation for saving match
      this.closeModal()
      this.loadData()
    }
  }
}
</script>