import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

/**
 * User Store - Pinia
 *
 * Central store for authenticated user data and permission computed properties.
 *
 * ⚠️ CRITICAL SECURITY WARNING:
 * Frontend permission checks are for USER EXPERIENCE ONLY.
 * They do NOT provide security. An attacker can modify this store or bypass frontend checks.
 *
 * Real security is enforced by:
 * - Layer 1: PostgreSQL Row-Level Security (database)
 * - Layer 2: HTTP Middleware (routes - RBACFilter)
 * - Layer 3: Service Guards (business logic - InvoiceGuard, etc.)
 *
 * This store helps improve UX by:
 * - Hiding irrelevant features for the current role
 * - Preventing accidental unauthorized actions
 * - Providing clear visual feedback on user capabilities
 */
export const useUserStore = defineStore('user', () => {
  // ===== State =====
  const id = ref(null)
  const email = ref(null)
  const role = ref(null)
  const agencyId = ref(null)
  const firstName = ref(null)
  const lastName = ref(null)

  // ===== Computed Properties (Permission Checks) =====

  /**
   * Can user view financial features?
   * - Owner: Yes (all agencies)
   * - Agency: Yes (their agency)
   * - Direct Client: Yes (assigned clients)
   * - End Client: NO (financial restriction)
   */
  const canViewFinancials = computed(() => {
    return ['owner', 'agency', 'direct_client'].includes(role.value)
  })

  /**
   * Can user manage users?
   * - Owner: Yes
   * - Agency: No
   * - Direct Client: No
   * - End Client: No
   */
  const canManageUsers = computed(() => {
    return role.value === 'owner'
  })

  /**
   * Can user manage agency settings?
   * - Owner: Yes (all agencies)
   * - Agency: Yes (their agency only)
   * - Direct Client: No
   * - End Client: No
   */
  const canManageAgencySettings = computed(() => {
    return ['owner', 'agency'].includes(role.value)
  })

  /**
   * Can user access admin features?
   * - Owner: Yes
   * - All others: No
   */
  const canAccessAdmin = computed(() => {
    return role.value === 'owner'
  })

  // ===== Role Convenience Checks =====

  const isOwner = computed(() => role.value === 'owner')
  const isAgency = computed(() => role.value === 'agency')
  const isDirectClient = computed(() => role.value === 'direct_client')
  const isEndClient = computed(() => role.value === 'end_client')

  // ===== User Display Data =====

  const fullName = computed(() => {
    if (!firstName.value || !lastName.value) return email.value
    return `${firstName.value} ${lastName.value}`
  })

  const initials = computed(() => {
    if (!firstName.value || !lastName.value) {
      return email.value ? email.value.substring(0, 2).toUpperCase() : '??'
    }
    return `${firstName.value.charAt(0)}${lastName.value.charAt(0)}`.toUpperCase()
  })

  const roleDisplay = computed(() => {
    const roleMap = {
      owner: 'Owner',
      agency: 'Agency User',
      direct_client: 'Direct Client',
      end_client: 'End Client'
    }
    return roleMap[role.value] || 'Unknown'
  })

  // ===== Actions =====

  /**
   * Initialize user store with data from server
   * Called once on application mount with session data
   */
  function init(userData) {
    if (!userData) {
      console.warn('User store initialized with null userData')
      return
    }

    id.value = userData.id
    email.value = userData.email
    role.value = userData.role
    agencyId.value = userData.agency_id
    firstName.value = userData.first_name || userData.name?.split(' ')[0]
    lastName.value = userData.last_name || userData.name?.split(' ')[1]
  }

  /**
   * Clear user store (logout)
   */
  function clear() {
    id.value = null
    email.value = null
    role.value = null
    agencyId.value = null
    firstName.value = null
    lastName.value = null
  }

  /**
   * Check if user is authenticated
   */
  const isAuthenticated = computed(() => {
    return id.value !== null && role.value !== null
  })

  return {
    // State
    id,
    email,
    role,
    agencyId,
    firstName,
    lastName,

    // Computed - Permissions
    canViewFinancials,
    canManageUsers,
    canManageAgencySettings,
    canAccessAdmin,

    // Computed - Role Checks
    isOwner,
    isAgency,
    isDirectClient,
    isEndClient,

    // Computed - Display
    fullName,
    initials,
    roleDisplay,
    isAuthenticated,

    // Actions
    init,
    clear
  }
})
