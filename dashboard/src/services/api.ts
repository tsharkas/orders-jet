import axios, { AxiosInstance, AxiosResponse } from 'axios';

// API service for WordPress REST API communication
class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = axios.create({
      baseURL: window.OrdersJetConfig?.apiUrl || '/wp-json/orders-jet/v1',
      timeout: 10000,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.OrdersJetConfig?.nonce || '',
      },
    });

    // Request interceptor
    this.api.interceptors.request.use(
      (config) => {
        // Add nonce to every request
        if (window.OrdersJetConfig?.nonce) {
          config.headers['X-WP-Nonce'] = window.OrdersJetConfig.nonce;
        }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );

    // Response interceptor
    this.api.interceptors.response.use(
      (response) => {
        return response;
      },
      (error) => {
        if (error.response?.status === 401) {
          // Handle unauthorized access
          console.error('Unauthorized access');
        }
        return Promise.reject(error);
      }
    );
  }

  // Dashboard data
  async getDashboardData(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/dashboard');
    return response.data;
  }

  // Tables management
  async getTables(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/tables');
    return response.data;
  }

  async updateTableStatus(tableId: number, status: string): Promise<any> {
    const response: AxiosResponse = await this.api.post(`/tables/${tableId}/status`, {
      status,
    });
    return response.data;
  }

  async assignWaiterToTable(tableId: number, waiterId: number): Promise<any> {
    const response: AxiosResponse = await this.api.post(`/tables/${tableId}/assign`, {
      waiter_id: waiterId,
    });
    return response.data;
  }

  // Orders management
  async getOrders(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/orders');
    return response.data;
  }

  async updateOrderStatus(orderId: number, status: string): Promise<any> {
    const response: AxiosResponse = await this.api.post(`/orders/${orderId}/status`, {
      status,
    });
    return response.data;
  }

  async getOrderDetails(orderId: number): Promise<any> {
    const response: AxiosResponse = await this.api.get(`/orders/${orderId}`);
    return response.data;
  }

  // Staff management
  async getStaff(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/staff');
    return response.data;
  }

  async assignStaffRole(userId: number, role: string): Promise<any> {
    const response: AxiosResponse = await this.api.post(`/staff/${userId}/role`, {
      role,
    });
    return response.data;
  }

  // Analytics
  async getAnalytics(dateRange?: string): Promise<any> {
    const params = dateRange ? { date_range: dateRange } : {};
    const response: AxiosResponse = await this.api.get('/analytics', { params });
    return response.data;
  }

  // Real-time updates
  async getTableUpdates(lastCheck?: number): Promise<any> {
    const params = lastCheck ? { last_check: lastCheck } : {};
    const response: AxiosResponse = await this.api.get('/tables/updates', { params });
    return response.data;
  }

  async getOrderUpdates(lastCheck?: number): Promise<any> {
    const params = lastCheck ? { last_check: lastCheck } : {};
    const response: AxiosResponse = await this.api.get('/orders/updates', { params });
    return response.data;
  }

  async getNotifications(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/notifications');
    return response.data;
  }

  // Payment methods
  async getPaymentMethods(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/payments/methods');
    return response.data;
  }

  async getPaymentStats(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/payments/stats');
    return response.data;
  }

  // Delivery management
  async getDeliveryOrders(): Promise<any> {
    const response: AxiosResponse = await this.api.get('/delivery/orders');
    return response.data;
  }

  async updateDeliveryStatus(orderId: number, status: string): Promise<any> {
    const response: AxiosResponse = await this.api.post(`/delivery/${orderId}/status`, {
      status,
    });
    return response.data;
  }
}

// Export singleton instance
export const apiService = new ApiService();
export default apiService;
