<template>
  <div class="payment-cancel py-8">
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center py-12">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-8">
          <i class="fas fa-exclamation-triangle text-yellow-600 text-6xl mb-4"></i>
          <h2 class="text-2xl font-bold text-yellow-800 mb-4">Paiement Annulé</h2>
          <p class="text-yellow-700 mb-6">
            Votre paiement a été annulé. Votre réservation est toujours en attente.
          </p>
          <div class="space-y-3">
            <router-link to="/profile" class="btn-primary w-full block text-center">
              <i class="fas fa-user mr-2"></i>
              Voir mes réservations
            </router-link>
            <router-link to="/matches" class="btn-secondary w-full block text-center">
              <i class="fas fa-arrow-left mr-2"></i>
              Retour aux matchs
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PaymentCancel'
}
</script>