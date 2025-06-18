<template>
  <div class="home">
    <!-- Hero Section -->
    <section class="relative h-screen flex items-center justify-center morocco-gradient">
      <div class="absolute inset-0 bg-black opacity-30"></div>
      <div class="relative z-10 text-center text-white px-4">
        <h1 class="text-5xl md:text-7xl font-bold mb-6">
          <i class="fas fa-futbol gold-accent mr-4"></i>
          Billetterie Football
        </h1>
        <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">
          Réservez vos places pour les matchs du championnat marocain en quelques clics
        </p>
        <div class="space-x-4">
          <router-link to="/matches" class="btn-primary text-lg px-8 py-3">
            <i class="fas fa-ticket-alt mr-2"></i>
            Voir les Matchs
          </router-link>
          <router-link to="/register" class="btn-secondary text-lg px-8 py-3">
            <i class="fas fa-user-plus mr-2"></i>
            S'inscrire
          </router-link>
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-gray-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-bold text-gray-900 mb-4">
            Pourquoi choisir notre plateforme ?
          </h2>
          <p class="text-xl text-gray-600 max-w-3xl mx-auto">
            Une expérience de réservation moderne, sécurisée et adaptée aux supporters du football marocain
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-bolt text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Réservation Rapide</h3>
            <p class="text-gray-600">
              Sélectionnez votre match, choisissez vos places et payez en moins de 2 minutes
            </p>
          </div>

          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-shield-alt text-green-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Paiement Sécurisé</h3>
            <p class="text-gray-600">
              Transactions sécurisées via PayPal avec protection complète de vos données
            </p>
          </div>

          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-mobile-alt text-blue-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Billets Électroniques</h3>
            <p class="text-gray-600">
              Recevez vos billets PDF avec QR code directement par email
            </p>
          </div>

          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-chart-line text-yellow-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Prix Dynamiques</h3>
            <p class="text-gray-600">
              Profitez des meilleurs prix avec notre système de tarification intelligente
            </p>
          </div>

          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-star text-purple-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Programme Fidélité</h3>
            <p class="text-gray-600">
              Gagnez des points à chaque achat et bénéficiez de réductions exclusives
            </p>
          </div>

          <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
              <i class="fas fa-info-circle text-indigo-600 text-2xl"></i>
            </div>
            <h3 class="text-xl font-bold mb-4">Infos Pratiques</h3>
            <p class="text-gray-600">
              Découvrez les informations sur les villes, transports et activités
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 morocco-gradient">
      <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
        <h2 class="text-4xl font-bold text-white mb-6">
          Prêt à vivre l'émotion du football marocain ?
        </h2>
        <p class="text-xl text-white mb-8">
          Rejoignez des milliers de supporters qui font confiance à notre plateforme
        </p>
        <router-link to="/matches" class="btn-primary text-lg px-8 py-3 bg-white text-red-600 hover:bg-gray-100">
          <i class="fas fa-rocket mr-2"></i>
          Commencer Maintenant
        </router-link>
      </div>
    </section>
  </div>
</template>

<script>
export default {
  name: 'HomeView'
}
</script>