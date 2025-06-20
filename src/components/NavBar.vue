<template>
  <nav class="fixed top-0 left-0 right-0 z-50 morocco-gradient shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex items-center">
          <router-link to="/" class="flex items-center space-x-2">
            <i class="fas fa-futbol text-white text-2xl"></i>
            <span class="text-white font-bold text-xl">BilletFootball.ma</span>
          </router-link>
        </div>

        <div class="hidden md:block">
          <div class="ml-10 flex items-baseline space-x-4">
            <router-link to="/" class="nav-link">
              <i class="fas fa-home mr-1"></i>Accueil
            </router-link>
            <router-link to="/matches" class="nav-link">
              <i class="fas fa-calendar mr-1"></i>Matchs
            </router-link>
            <template v-if="isAuthenticated">
              <router-link to="/profile" class="nav-link">
                <i class="fas fa-user mr-1"></i>Profil
              </router-link>
              <!-- Bouton Admin visible seulement si admin authentifié -->
              <router-link v-if="isAdminAuthenticated" to="/admin" class="admin-link">
                <i class="fas fa-cog mr-1"></i>Admin
              </router-link>
              <button @click="logout" class="nav-link">
                <i class="fas fa-sign-out-alt mr-1"></i>Déconnexion
              </button>
            </template>
            <template v-else>
              <router-link to="/login" class="nav-link">
                <i class="fas fa-sign-in-alt mr-1"></i>Connexion
              </router-link>
              <router-link to="/register" class="nav-link">
                <i class="fas fa-user-plus mr-1"></i>Inscription
              </router-link>
            </template>
          </div>
        </div>

        <!-- Mobile menu button -->
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden text-white">
          <i class="fas fa-bars text-xl"></i>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div v-if="mobileMenuOpen" class="md:hidden bg-red-800">
      <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
        <router-link to="/" class="mobile-nav-link" @click="mobileMenuOpen = false">
          <i class="fas fa-home mr-2"></i>Accueil
        </router-link>
        <router-link to="/matches" class="mobile-nav-link" @click="mobileMenuOpen = false">
          <i class="fas fa-calendar mr-2"></i>Matchs
        </router-link>
        <template v-if="isAuthenticated">
          <router-link to="/profile" class="mobile-nav-link" @click="mobileMenuOpen = false">
            <i class="fas fa-user mr-2"></i>Profil
          </router-link>
          <!-- Bouton Admin Mobile visible seulement si admin authentifié -->
          <router-link v-if="isAdminAuthenticated" to="/admin" class="mobile-admin-link" @click="mobileMenuOpen = false">
            <i class="fas fa-cog mr-2"></i>Admin
          </router-link>
          <button @click="logout" class="mobile-nav-link w-full text-left">
            <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
          </button>
        </template>
        <template v-else>
          <router-link to="/login" class="mobile-nav-link" @click="mobileMenuOpen = false">
            <i class="fas fa-sign-in-alt mr-2"></i>Connexion
          </router-link>
          <router-link to="/register" class="mobile-nav-link" @click="mobileMenuOpen = false">
            <i class="fas fa-user-plus mr-2"></i>Inscription
          </router-link>
        </template>
      </div>
    </div>
  </nav>
</template>

<script>
export default {
  name: 'NavBar',
  data() {
    return {
      mobileMenuOpen: false
    }
  },
  computed: {
    isAuthenticated() {
      return !!localStorage.getItem('token')
    },
    isAdminAuthenticated() {
      return localStorage.getItem('admin_token') === 'admin123'
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('admin_token'); // Supprimer aussi le token admin
      this.$router.push('/login'); 
    }
  }
}
</script>

<style scoped>
.nav-link {
  @apply text-white hover:text-yellow-300 px-3 py-2 rounded-md text-sm font-medium transition-colors;
}

.mobile-nav-link {
  @apply text-white hover:text-yellow-300 block px-3 py-2 rounded-md text-base font-medium transition-colors;
}

/* Styles spéciaux pour le bouton Admin */
.admin-link {
  @apply text-white hover:text-yellow-300 px-3 py-2 rounded-md text-sm font-medium transition-colors;
  @apply bg-red-700 hover:bg-red-600;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.mobile-admin-link {
  @apply text-white hover:text-yellow-300 block px-3 py-2 rounded-md text-base font-medium transition-colors;
  @apply bg-red-700 hover:bg-red-600;
  border: 1px solid rgba(255, 255, 255, 0.3);
}
</style>