// Orders Jet Dashboard - Main JavaScript
// This is a temporary build for testing - will be replaced by actual React build

(function() {
  'use strict';

  // Configuration from WordPress
  const config = window.OrdersJetConfig || {
    apiUrl: '/wp-json/orders-jet/v1/',
    userRole: 'oj_manager',
    userId: 1,
    userName: 'مدير المطعم',
    siteUrl: 'http://localhost',
    pluginUrl: '/wp-content/plugins/orders-jet-integration/',
    websocketUrl: 'ws://localhost:8080'
  };

  // Mock data for testing
  const mockData = {
    metrics: {
      todayOrders: 45,
      todayRevenue: 12500,
      occupiedTables: 8,
      staffActivity: 85
    },
    tables: [
      { id: 1, number: 'T01', status: 'occupied', assignedWaiter: 'أحمد محمد', sessionTotal: 250 },
      { id: 2, number: 'T02', status: 'available', assignedWaiter: '', sessionTotal: 0 },
      { id: 3, number: 'T03', status: 'occupied', assignedWaiter: 'فاطمة علي', sessionTotal: 180 },
      { id: 4, number: 'T04', status: 'cleaning', assignedWaiter: '', sessionTotal: 0 },
    ],
    orders: [
      { id: 1, orderNumber: '#1234', tableNumber: 'T01', status: 'completed', total: 250, items: 4 },
      { id: 2, orderNumber: '#1235', tableNumber: 'T03', status: 'preparing', total: 180, items: 2 },
      { id: 3, orderNumber: '#1236', tableNumber: 'T05', status: 'ready', total: 320, items: 3 },
      { id: 4, orderNumber: '#1237', tableNumber: 'T07', status: 'completed', total: 150, items: 2 },
    ]
  };

  // Dashboard class
  class OrdersJetDashboard {
    constructor() {
      this.init();
    }

    init() {
      this.renderDashboard();
      this.bindEvents();
      this.startRealTimeUpdates();
    }

    renderDashboard() {
      const container = document.getElementById('orders-jet-manager-dashboard') || 
                       document.getElementById('orders-jet-kitchen-dashboard') ||
                       document.getElementById('orders-jet-waiter-dashboard');
      
      if (!container) {
        console.error('Orders Jet Dashboard container not found');
        return;
      }

      container.innerHTML = this.getDashboardHTML();
    }

    getDashboardHTML() {
      return `
        <div class="dashboard-container">
          ${this.getSidebarHTML()}
          <div class="main-content">
            ${this.getHeaderHTML()}
            ${this.getMetricsHTML()}
            ${this.getChartsHTML()}
            ${this.getOrdersHTML()}
          </div>
        </div>
      `;
    }

    getSidebarHTML() {
      return `
        <div class="sidebar">
          <div class="sidebar-header">
            <div class="sidebar-logo">شَهْبَنْدَر</div>
            <div style="font-size: 14px; color: #666;">Orders Jet v1.0.0</div>
          </div>
          
          <div class="sidebar-user">
            <div class="user-name">مرحباً، ${config.userName}</div>
            <div class="user-role">مدير المطعم</div>
          </div>
          
          <nav class="sidebar-nav">
            <a href="#" class="nav-item active">
              <i>📊</i> نظرة عامة
            </a>
            <a href="#" class="nav-item">
              <i>🪑</i> الطاولات
            </a>
            <a href="#" class="nav-item">
              <i>📋</i> الطلبات
            </a>
            <a href="#" class="nav-item">
              <i>👥</i> الموظفين
            </a>
            <a href="#" class="nav-item">
              <i>📈</i> التحليلات
            </a>
            <a href="#" class="nav-item">
              <i>⚙️</i> الإعدادات
            </a>
            <a href="#" class="nav-item">
              <i>❓</i> المساعدة
            </a>
          </nav>
        </div>
      `;
    }

    getHeaderHTML() {
      return `
        <div class="header">
          <div class="header-title">لوحة التحكم - المدير</div>
          <div class="header-actions">
            <button class="refresh-btn" id="refresh-btn">
              🔄 تحديث
            </button>
          </div>
        </div>
      `;
    }

    getMetricsHTML() {
      const { metrics } = mockData;
      return `
        <div class="metrics-grid">
          <div class="metric-card">
            <div class="metric-header">
              <div class="metric-title">طلبات اليوم</div>
              <div class="metric-icon primary">🛒</div>
            </div>
            <div class="metric-value primary">${metrics.todayOrders}</div>
            <div class="metric-change positive">
              ↗️ +12% من أمس
            </div>
          </div>
          
          <div class="metric-card success">
            <div class="metric-header">
              <div class="metric-title">إيرادات اليوم</div>
              <div class="metric-icon success">💰</div>
            </div>
            <div class="metric-value success">${metrics.todayRevenue.toLocaleString()} ج.م</div>
            <div class="metric-change positive">
              ↗️ +8% من أمس
            </div>
          </div>
          
          <div class="metric-card warning">
            <div class="metric-header">
              <div class="metric-title">الطاولات المشغولة</div>
              <div class="metric-icon warning">🪑</div>
            </div>
            <div class="metric-value warning">${metrics.occupiedTables}</div>
            <div class="metric-change">
              من أصل 15 طاولة
            </div>
          </div>
          
          <div class="metric-card secondary">
            <div class="metric-header">
              <div class="metric-title">نشاط الموظفين</div>
              <div class="metric-icon secondary">👥</div>
            </div>
            <div class="metric-value secondary">${metrics.staffActivity}%</div>
            <div class="metric-change">
              👥 12 موظف نشط
            </div>
          </div>
        </div>
      `;
    }

    getChartsHTML() {
      return `
        <div class="charts-grid">
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title">الإيرادات اليومية</div>
              <div class="chart-subtitle">تتبع الإيرادات على مدار اليوم</div>
            </div>
            <div class="chart-placeholder">
              📈 مخطط الإيرادات التفاعلي
              <br><small>سيتم استبداله بمخطط Recharts</small>
            </div>
          </div>
          
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title">حالة الطلبات</div>
              <div class="chart-subtitle">توزيع الطلبات حسب الحالة</div>
            </div>
            <div class="chart-placeholder">
              🥧 مخطط دائري للطلبات
              <br><small>سيتم استبداله بمخطط Recharts</small>
            </div>
          </div>
        </div>
      `;
    }

    getOrdersHTML() {
      const { orders } = mockData;
      return `
        <div class="bottom-grid">
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title">طرق الدفع</div>
              <div class="chart-subtitle">توزيع المبيعات حسب طريقة الدفع</div>
            </div>
            <div class="chart-placeholder">
              📊 مخطط طرق الدفع
              <br><small>فواتيرك، إنستاباي، والد، نقدي</small>
            </div>
          </div>
          
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title">الطلبات الأخيرة</div>
              <div class="chart-subtitle">آخر ${orders.length} طلبات</div>
            </div>
            <div class="orders-list">
              ${orders.map(order => `
                <div class="order-item">
                  <div class="order-info">
                    <h4>${order.orderNumber}</h4>
                    <p>الطاولة ${order.tableNumber} • ${order.items} منتج</p>
                  </div>
                  <div class="order-details">
                    <div class="order-total">${order.total} ج.م</div>
                    <div class="order-status ${order.status}">${this.getStatusText(order.status)}</div>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      `;
    }

    getStatusText(status) {
      const statusMap = {
        'completed': 'مكتمل',
        'preparing': 'قيد التحضير',
        'ready': 'جاهز',
        'placed': 'تم الطلب'
      };
      return statusMap[status] || status;
    }

    bindEvents() {
      // Refresh button
      document.addEventListener('click', (e) => {
        if (e.target.id === 'refresh-btn') {
          this.refreshData();
        }
      });

      // Navigation items
      document.addEventListener('click', (e) => {
        if (e.target.classList.contains('nav-item')) {
          e.preventDefault();
          this.handleNavigation(e.target);
        }
      });
    }

    handleNavigation(item) {
      // Remove active class from all items
      document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
      // Add active class to clicked item
      item.classList.add('active');
      
      console.log('Navigation clicked:', item.textContent.trim());
    }

    refreshData() {
      const refreshBtn = document.getElementById('refresh-btn');
      if (refreshBtn) {
        refreshBtn.innerHTML = '⏳ جاري التحديث...';
        refreshBtn.disabled = true;
        
        // Simulate API call
        setTimeout(() => {
          refreshBtn.innerHTML = '🔄 تحديث';
          refreshBtn.disabled = false;
          console.log('Data refreshed');
        }, 1000);
      }
    }

    startRealTimeUpdates() {
      // Simulate real-time updates every 30 seconds
      setInterval(() => {
        console.log('Real-time update check...');
        // In real implementation, this would check for new orders, table changes, etc.
      }, 30000);
    }
  }

  // Initialize dashboard when DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Orders Jet Dashboard initializing...');
    console.log('Configuration:', config);
    
    // Check if we're in WordPress admin
    if (typeof wp !== 'undefined') {
      console.log('WordPress environment detected');
    }
    
    new OrdersJetDashboard();
  });

})();
