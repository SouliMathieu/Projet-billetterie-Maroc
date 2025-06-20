import { createRouter, createWebHistory } from 'vue-router'

// 1. Importations statiques avec chemin relatif
import HomeView from '../views/HomeView.vue'
import LoginView from '../views/auth/LoginView.vue'
import RegisterView from '../views/auth/RegisterView.vue'
import FootballMatchesView from '../views/FootballMatchesView.vue'
// Utilisez un chemin relatif au lieu de l'alias @
import AdminDashboard from '../views/admin/AdminDashboard.vue'

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
  {
    path: '/admin',
    name: 'admin',
    component: AdminDashboard,
    // SUPPRIMÉ: meta: { requiresAuth: true }
    // La page admin gère sa propre authentification
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  
  // Permettre l'accès libre à la route admin
  if (to.name === 'admin') {
    return next()
  }
  
  if (to.meta.requiresAuth && !token) {
    return next('/login')
  }
  
  if (to.meta.forGuests && token) {
    return next('/profile')
  }

  next()
})

export default router