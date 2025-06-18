import { createRouter, createWebHistory } from 'vue-router'

// 1. Importations statiques (garanties sans erreur)
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/auth/LoginView.vue'
import RegisterView from '../views/auth/RegisterView.vue'
import FootballMatchesView from '../views/FootballMatchesView.vue'

// 2. Importations dynamiques simplifiées
const ReservationFormView = () => import('../views/ReservationFormView.vue')
const ProfileView = () => import('../views/ProfileView.vue')
const PaymentView = () => import('../views/PaymentView.vue')

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { forGuests: true }
  },
  {
    path: '/register',
    name: 'register',
    component: RegisterView,
    meta: { forGuests: true }
  },
  {
    path: '/matches',
    name: 'matches',
    component: FootballMatchesView
  },
  {
    path: '/reservation/:matchId',
    name: 'reservation',
    component: ReservationFormView,
    props: true,
    meta: { requiresAuth: true }
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfileView,
    meta: { requiresAuth: true }
  },
  {
    path: '/payment/:reservationId',
    name: 'payment',
    component: PaymentView,
    props: true,
    meta: { requiresAuth: true }
  },
  // Fallback pour les routes inconnues
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// 3. Garde de navigation ultra-simplifiée
router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  
  // Si la route nécessite une connexion
  if (to.meta.requiresAuth && !token) {
    return next('/login')
  }
  
  // Si un utilisateur connecté essaie d'accéder à une page pour invités
  if (to.meta.forGuests && token) {
    return next('/profile')
  }

  next()
})

export default router