import { useUserStore } from '@/stores/user'

/**
 * usePermissions Composable
 *
 * Reusable permission checking logic for Vue components.
 *
 * ⚠️ SECURITY WARNING:
 * These checks are for UX only. Backend MUST enforce all authorization.
 *
 * Usage:
 * ```vue
 * <script setup>
 * import { usePermissions } from '@/composables/usePermissions'
 *
 * const { can } = usePermissions()
 * </script>
 *
 * <template>
 *   <button v-if="can('delete-invoices')">Delete</button>
 * </template>
 * ```
 */
export function usePermissions() {
  const userStore = useUserStore()

  /**
   * Check if user has a specific permission
   *
   * @param {string} permission - Permission key to check
   * @returns {boolean} - True if user has permission
   */
  const can = (permission) => {
    // Map of permission keys to computed checks
    const permissions = {
      // Financial Permissions
      'view-financials': userStore.canViewFinancials,
      'create-invoices': ['owner', 'agency'].includes(userStore.role),
      'edit-invoices': ['owner', 'agency'].includes(userStore.role),
      'delete-invoices': userStore.isOwner,
      'create-quotes': ['owner', 'agency'].includes(userStore.role),
      'edit-quotes': ['owner', 'agency'].includes(userStore.role),
      'delete-quotes': userStore.isOwner,
      'view-payments': userStore.canViewFinancials,
      'process-payments': ['owner', 'agency'].includes(userStore.role),

      // Project Permissions
      'create-projects': ['owner', 'agency'].includes(userStore.role),
      'edit-projects': ['owner', 'agency'].includes(userStore.role),
      'delete-projects': userStore.isOwner,
      'manage-project-members': ['owner', 'agency'].includes(userStore.role),

      // Client Permissions
      'create-clients': ['owner', 'agency'].includes(userStore.role),
      'edit-clients': ['owner', 'agency'].includes(userStore.role),
      'delete-clients': userStore.isOwner,
      'manage-client-users': ['owner', 'agency'].includes(userStore.role),

      // User Management
      'manage-users': userStore.canManageUsers,
      'create-users': userStore.isOwner,
      'edit-users': userStore.isOwner,
      'delete-users': userStore.isOwner,

      // Agency Settings
      'manage-agency-settings': userStore.canManageAgencySettings,
      'view-agency-settings': userStore.canManageAgencySettings,

      // Admin Features
      'access-admin': userStore.canAccessAdmin,
      'manage-global-settings': userStore.isOwner,
      'view-system-logs': userStore.isOwner,
    }

    return permissions[permission] ?? false
  }

  /**
   * Check if user has ANY of the specified permissions
   *
   * @param {string[]} permissionList - Array of permission keys
   * @returns {boolean} - True if user has at least one permission
   */
  const canAny = (permissionList) => {
    return permissionList.some(permission => can(permission))
  }

  /**
   * Check if user has ALL of the specified permissions
   *
   * @param {string[]} permissionList - Array of permission keys
   * @returns {boolean} - True if user has all permissions
   */
  const canAll = (permissionList) => {
    return permissionList.every(permission => can(permission))
  }

  /**
   * Get user's role
   *
   * @returns {string|null} - Current user role
   */
  const getRole = () => {
    return userStore.role
  }

  /**
   * Check if user has specific role
   *
   * @param {string} roleName - Role to check
   * @returns {boolean} - True if user has role
   */
  const hasRole = (roleName) => {
    return userStore.role === roleName
  }

  /**
   * Check if user has any of the specified roles
   *
   * @param {string[]} roleList - Array of role names
   * @returns {boolean} - True if user has any of the roles
   */
  const hasAnyRole = (roleList) => {
    return roleList.includes(userStore.role)
  }

  return {
    can,
    canAny,
    canAll,
    getRole,
    hasRole,
    hasAnyRole
  }
}
