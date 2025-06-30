<template>
  <div class="team-logo-container">
    <img 
      :src="logoUrl" 
      :alt="`Logo ${teamName}`"
      :class="logoClasses"
      @error="handleImageError"
      @load="imageLoaded = true"
    />
    <div v-if="showName" class="team-name">
      {{ teamName }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'TeamLogo',
  props: {
    logoUrl: {
      type: String,
      required: true
    },
    teamName: {
      type: String,
      required: true
    },
    size: {
      type: String,
      default: 'medium', // small, medium, large
      validator: value => ['small', 'medium', 'large'].includes(value)
    },
    showName: {
      type: Boolean,
      default: true
    },
    rounded: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      imageLoaded: false,
      imageError: false
    }
  },
  computed: {
    logoClasses() {
      const baseClasses = 'object-contain transition-transform duration-300 hover:scale-110';
      const sizeClasses = {
        small: 'w-8 h-8',
        medium: 'w-16 h-16',
        large: 'w-24 h-24'
      };
      const roundedClass = this.rounded ? 'rounded-full' : 'rounded-lg';
      
      return `${baseClasses} ${sizeClasses[this.size]} ${roundedClass} border-2 border-gray-200`;
    }
  },
  methods: {
    handleImageError(event) {
      this.imageError = true;
      event.target.src = '/default-team-logo.png';
    }
  }
}
</script>

<style scoped>
.team-logo-container {
  @apply flex flex-col items-center space-y-2;
}

.team-name {
  @apply text-sm font-medium text-gray-700 text-center;
}
</style>
