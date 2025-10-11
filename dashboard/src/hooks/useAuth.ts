import { useState, useEffect } from 'react';

export interface User {
  id: number;
  role: string;
  name: string;
  permissions: string[];
}

export interface AuthState {
  user: User | null;
  loading: boolean;
  error: string | null;
}

export const useAuth = (): AuthState => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchUserData = async () => {
      try {
        // Get user data from WordPress configuration
        if (window.OrdersJetConfig) {
          const config = window.OrdersJetConfig;
          
          // Validate user role
          if (!['oj_manager', 'oj_kitchen', 'oj_waiter'].includes(config.userRole)) {
            throw new Error('Invalid user role');
          }

          // Get user permissions based on role
          const permissions = getUserPermissions(config.userRole);

          setUser({
            id: config.userId,
            role: config.userRole,
            name: config.userName,
            permissions,
          });
        } else {
          throw new Error('Configuration not available');
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Authentication failed');
      } finally {
        setLoading(false);
      }
    };

    fetchUserData();
  }, []);

  return { user, loading, error };
};

const getUserPermissions = (role: string): string[] => {
  const permissions: { [key: string]: string[] } = {
    oj_manager: [
      'manage_oj_staff',
      'view_oj_reports',
      'manage_oj_tables',
      'manage_oj_orders',
      'close_oj_tables',
      'view_oj_financials',
      'assign_oj_waiters',
      'view_oj_analytics',
      'configure_oj_system',
      'access_oj_manager_dashboard',
      'access_oj_kitchen_dashboard',
      'access_oj_waiter_dashboard',
    ],
    oj_kitchen: [
      'view_oj_kitchen_orders',
      'update_oj_order_status',
      'view_oj_kitchen_display',
      'mark_oj_order_preparing',
      'mark_oj_order_ready',
      'view_oj_order_details',
      'access_oj_kitchen_dashboard',
    ],
    oj_waiter: [
      'claim_oj_tables',
      'view_oj_assigned_tables',
      'deliver_oj_orders',
      'collect_oj_payments',
      'generate_oj_invoices',
      'close_oj_table_session',
      'view_oj_order_details',
      'update_oj_table_status',
      'access_oj_waiter_dashboard',
    ],
  };

  return permissions[role] || [];
};
