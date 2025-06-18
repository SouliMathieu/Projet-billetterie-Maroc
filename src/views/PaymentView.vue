<template>
  <div class="payment-view py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <i class="fas fa-spinner fa-spin text-4xl text-red-600 mb-4"></i>
        <p class="text-gray-600">Traitement du paiement...</p>
      </div>

      <!-- Payment Form -->
      <div v-else-if="!paymentCompleted" class="card p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">
          <i class="fas fa-credit-card text-red-600 mr-3"></i>
          Finaliser le Paiement
        </h1>

        <div v-if="reservation" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          <!-- Résumé de la commande -->
          <div class="bg-gray-50 p-6 rounded-lg">
            <h2 class="text-xl font-semibold mb-4">Résumé de votre commande</h2>
            
            <div class="space-y-3">
              <div class="flex justify-between">
                <span>Match:</span>
                <span class="font-semibold">{{ reservation.equipe_domicile }} vs {{ reservation.equipe_exterieur }}</span>
              </div>
              <div class="flex justify-between">
                <span>Date:</span>
                <span>{{ formatDate(reservation.date_match) }}</span>
              </div>
              <div class="flex justify-between">
                <span>Stade:</span>
                <span>{{ reservation.stade_nom }}, {{ reservation.stade_ville }}</span>
              </div>
              <div class="flex justify-between">
                <span>Catégorie:</span>
                <span>{{ reservation.categorie }}</span>
              </div>
              <div class="flex justify-between">
                <span>Nombre de billets:</span>
                <span>{{ reservation.nombre_billets }}</span>
              </div>
              <hr class="my-4">
              <div class="flex justify-between text-xl font-bold text-red-600">
                <span>Total:</span>
                <span>{{ reservation.prix_total }} DH</span>
              </div>
            </div>
          </div>

          <!-- Options de paiement -->
          <div>
            <h2 class="text-xl font-semibold mb-4">Méthode de paiement</h2>
            
            <!-- Programme de fidélité -->
            <div v-if="userPoints > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
              <h3 class="font-semibold text-yellow-800 mb-2">
                <i class="fas fa-star mr-2"></i>
                Utiliser vos points de fidélité
              </h3>
              <p class="text-yellow-700 mb-3">Vous avez {{ userPoints }} points ({{ userPoints }} DH de réduction)</p>
              
              <div class="flex items-center space-x-4">
                <input
                  type="number"
                  v-model="pointsToUse"
                  :max="Math.min(userPoints, reservation.prix_total)"
                  min="0"
                  class="w-24 px-3 py-1 border border-yellow-300 rounded focus:outline-none focus:ring-2 focus:ring-yellow-500"
                  placeholder="0"
                />
                <button
                  @click="applyPoints"
                  class="btn-secondary text-sm"
                >
                  Appliquer
                </button>
              </div>
              
              <div v-if="discount > 0" class="mt-3 p-3 bg-green-50 border border-green-200 rounded">
                <p class="text-green-800">
                  <i class="fas fa-check mr-2"></i>
                  Réduction appliquée: -{{ discount }} DH
                </p>
              </div>
            </div>

            <!-- Total final -->
            <div class="bg-gray-100 p-4 rounded-lg mb-6">
              <div class="flex justify-between items-center">
                <span class="text-lg">Total à payer:</span>
                <span class="text-2xl font-bold text-red-600">{{ finalAmount }} DH</span>
              </div>
            </div>

            <!-- Bouton PayPal -->
            <div class="space-y-4">
              <button
                @click="initiatePayPalPayment"
                :disabled="processingPayment"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors flex items-center justify-center"
              >
                <span v-if="processingPayment">
                  <i class="fas fa-spinner fa-spin mr-2"></i>
                  Redirection vers PayPal...
                </span>
                <span v-else>
                  <i class="fab fa-paypal mr-2"></i>
                  Payer avec PayPal
                </span>
              </button>
              
              <p class="text-sm text-gray-600 text-center">
                Paiement sécurisé via PayPal. Vous serez redirigé vers PayPal pour finaliser votre achat.
              </p>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="error" class="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
          <p class="text-red-800">{{ error }}</p>
        </div>
      </div>

      <!-- Payment Success -->
      <div v-else class="text-center py-12">
        <div class="bg-green-50 border border-green-200 rounded-lg p-8 max-w-md mx-auto">
          <i class="fas fa-check-circle text-green-600 text-6xl mb-4"></i>
          <h2 class="text-2xl font-bold text-green-800 mb-4">Paiement Confirmé !</h2>
          <p class="text-green-700 mb-6">
            Votre réservation a été confirmée avec succès. Vous allez recevoir vos billets par email.
          </p>
          <div class="space-y-3">
            <button
              @click="downloadTickets"
              class="btn-primary w-full"
            >
              <i class="fas fa-download mr-2"></i>
              Télécharger les billets PDF
            </button>
            <router-link to="/profile" class="btn-secondary w-full block text-center">
              <i class="fas fa-user mr-2"></i>
              Voir mes réservations
            </router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'PaymentView',
  props: ['reservationId'],
  data() {
    return {
      reservation: null,
      loading: true,
      error: null,
      processingPayment: false,
      paymentCompleted: false,
      userPoints: 0,
      pointsToUse: 0,
      discount: 0
    }
  },
  computed: {
    finalAmount() {
      return Math.max(0, this.reservation?.prix_total - this.discount)
    }
  },
  mounted() {
    this.loadReservation()
    this.loadUserPoints()
    
    // Vérifier si on revient de PayPal
    const urlParams = new URLSearchParams(window.location.search)
    const paymentId = urlParams.get('paymentId')
    const payerId = urlParams.get('PayerID')
    
    if (paymentId && payerId) {
      this.capturePayPalPayment(paymentId)
    }
  },
  methods: {
    async loadReservation() {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get('http://localhost/Billet/backend/api/reservations.php', {
          headers: { 'Authorization': `Bearer ${token}` }
        })
        
        this.reservation = response.data.find(r => r.id == this.reservationId)
        
        if (!this.reservation) {
          this.error = 'Réservation non trouvée'
        }
      } catch (error) {
        this.error = 'Erreur lors du chargement de la réservation'
      } finally {
        this.loading = false
      }
    },
    
    async loadUserPoints() {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.get('http://localhost/Billet/backend/api/loyalty.php', {
          headers: { 'Authorization': `Bearer ${token}` }
        })
        this.userPoints = response.data.total_points
      } catch (error) {
        console.error('Erreur lors du chargement des points:', error)
      }
    },
    
    async applyPoints() {
      if (this.pointsToUse > 0 && this.pointsToUse <= this.userPoints) {
        try {
          const token = localStorage.getItem('token')
          const response = await axios.post('http://localhost/Billet/backend/api/loyalty.php', {
            action: 'use_points',
            points_to_use: this.pointsToUse
          }, {
            headers: { 'Authorization': `Bearer ${token}` }
          })
          
          this.discount = response.data.discount
        } catch (error) {
          this.error = 'Erreur lors de l\'application des points'
        }
      }
    },
    
    async initiatePayPalPayment() {
      this.processingPayment = true
      this.error = null
      
      try {
        const token = localStorage.getItem('token')
        const response = await axios.post('http://localhost/Billet/backend/api/paypal-payment.php', {
          action: 'create_order',
          reservation_id: this.reservationId
        }, {
          headers: { 'Authorization': `Bearer ${token}` }
        })
        
        // Rediriger vers PayPal
        window.location.href = response.data.approval_url
        
      } catch (error) {
        this.error = error.response?.data?.message || 'Erreur lors de l\'initialisation du paiement'
        this.processingPayment = false
      }
    },
    
async capturePayPalPayment(orderId) {
  try {
    this.loading = true;
    const token = localStorage.getItem('token');
    
    // 1. Capture the payment
    const paymentResponse = await axios.post(
      'http://localhost/Billet/backend/api/paypal-payment.php',
      { action: 'capture_order', order_id: orderId },
      { headers: { 'Authorization': `Bearer ${token}` } }
    );

    if (paymentResponse.data.status === "success") {
      const reservationId = paymentResponse.data.reservation_id;
      
      // 2. Generate PDF tickets
      const pdfResponse = await this.generateAndSendTickets(reservationId);
      
      // 3. Send email with tickets
      await axios.post(
        'http://localhost/Billet/backend/api/send-email-emailjs.php',
        { 
          reservation_id: reservationId,
          pdf_base64: pdfResponse.pdf_base64 
        },
        { headers: { 'Authorization': `Bearer ${token}` } }
      );
      
      // 4. Add loyalty points
      await this.addLoyaltyPoints(reservationId);
      
      // 5. Update UI
      this.paymentCompleted = true;
      this.loading = false;
      
      // Clean URL
      window.history.replaceState({}, document.title, window.location.pathname);
    }
  } catch (error) {
    console.error('Error in payment process:', error);
    this.error = error.response?.data?.message || 'Erreur lors du processus de paiement';
    this.loading = false;
  }
},
    
    async addLoyaltyPoints(reservationId) {
      try {
        const token = localStorage.getItem('token')
        await axios.post('http://localhost/Billet/backend/api/loyalty.php', {
          action: 'add_points',
          reservation_id: reservationId
        }, {
          headers: { 'Authorization': `Bearer ${token}` }
        })
      } catch (error) {
        console.error('Erreur lors de l\'ajout des points:', error)
      }
    },
    
// Dans votre frontend (PaymentView.vue)
async generateAndSendTickets(reservationId) {
  try {
    const token = localStorage.getItem('token');
    const response = await axios.post(
      'http://localhost/Billet/backend/api/generate-pdf.php',
      { reservation_id: reservationId },
      {
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );
    return response.data;
  } catch (error) {
    console.error("Erreur génération PDF:", error);
    throw error;
  }
},
    
    async downloadTickets() {
      try {
        const token = localStorage.getItem('token')
        const response = await axios.post('http://localhost/Billet/backend/api/generate-pdf.php', {
          reservation_id: this.reservationId
        }, {
          headers: { 'Authorization': `Bearer ${token}` }
        })
        
        // Télécharger le PDF
        const link = document.createElement('a')
        link.href = 'data:application/pdf;base64,' + response.data.pdf_base64
        link.download = `billets_reservation_${this.reservationId}.pdf`
        link.click()
        
      } catch (error) {
        this.error = 'Erreur lors du téléchargement des billets'
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
    }
  }
}
</script>