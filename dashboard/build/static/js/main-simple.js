// Simple Orders Jet Dashboard - Immediate Test Version
(function() {
  'use strict';

  // Configuration from WordPress
  const config = window.OrdersJetConfig || {
    apiUrl: '/wp-json/orders-jet/v1/',
    userRole: 'oj_manager',
    userId: '1',
    userName: 'Admin',
    userLanguage: 'en',
    siteUrl: window.location.origin,
    pluginUrl: window.location.origin + '/wp-content/plugins/orders-jet/',
    websocketUrl: 'ws://localhost:8080'
  };

  console.log('Orders Jet Dashboard initializing...', config);

  // Language configuration
  let language = config.userLanguage || 'en';
  const isRTL = language === 'ar';

  // Simple translations
  const translations = {
    en: {
      dashboard: 'Dashboard',
      overview: 'Overview',
      tables: 'Tables',
      orders: 'Orders',
      staff: 'Staff',
      analytics: 'Analytics',
      settings: 'Settings',
      help: 'Help',
      todayRevenue: "Today's Revenue",
      occupiedTables: 'Occupied Tables',
      staffActivity: 'Staff Activity',
      outOfTables: 'out of 15 tables',
      activeStaff: 'active staff'
    },
    ar: {
      dashboard: 'لوحة التحكم',
      overview: 'نظرة عامة',
      tables: 'الطاولات',
      orders: 'الطلبات',
      staff: 'الموظفين',
      analytics: 'التحليلات',
      settings: 'الإعدادات',
      help: 'المساعدة',
      todayRevenue: 'إيرادات اليوم',
      occupiedTables: 'الطاولات المشغولة',
      staffActivity: 'نشاط الموظفين',
      outOfTables: 'من أصل 15 طاولة',
      activeStaff: 'موظف نشط'
    }
  };

  function t(key) {
    return translations[language][key] || key;
  }

  function getDashboardHTML() {
    return `
      <div class="orders-jet-dashboard" dir="${isRTL ? 'rtl' : 'ltr'}">
        <div class="dashboard-header">
          <h1>Orders Jet ${t('dashboard')}</h1>
          <div class="header-actions">
            <button class="language-toggle" onclick="window.OrdersJetDashboard.toggleLanguage()">
              ${language === 'ar' ? 'EN' : 'عربي'}
            </button>
            <button class="refresh-btn" onclick="window.OrdersJetDashboard.refresh()">🔄</button>
          </div>
        </div>
        
        <div class="dashboard-content">
          <div class="sidebar">
            <nav class="dashboard-nav">
              <a href="#" class="nav-item active" data-section="overview">${t('overview')}</a>
              <a href="#" class="nav-item" data-section="tables">${t('tables')}</a>
              <a href="#" class="nav-item" data-section="orders">${t('orders')}</a>
              <a href="#" class="nav-item" data-section="staff">${t('staff')}</a>
              <a href="#" class="nav-item" data-section="analytics">${t('analytics')}</a>
              <a href="#" class="nav-item" data-section="settings">${t('settings')}</a>
              <a href="#" class="nav-item" data-section="help">${t('help')}</a>
            </nav>
          </div>
          
          <div class="main-content">
            <div class="metrics-grid">
              <div class="metric-card revenue">
                <div class="metric-icon">💰</div>
                <div class="metric-content">
                  <h3>${t('todayRevenue')}</h3>
                  <div class="metric-value">12,500 EGP</div>
                  <div class="metric-change">+8% from yesterday</div>
                </div>
              </div>
              
              <div class="metric-card tables">
                <div class="metric-icon">🪑</div>
                <div class="metric-content">
                  <h3>${t('occupiedTables')}</h3>
                  <div class="metric-value">8</div>
                  <div class="metric-change">${t('outOfTables')}</div>
                </div>
              </div>
              
              <div class="metric-card staff">
                <div class="metric-icon">👥</div>
                <div class="metric-content">
                  <h3>${t('staffActivity')}</h3>
                  <div class="metric-value">85%</div>
                  <div class="metric-change">12 ${t('activeStaff')}</div>
                </div>
              </div>
            </div>
            
            <div class="charts-section">
              <div class="chart-placeholder">
                <h3>Revenue Chart</h3>
                <p>Interactive revenue chart will be displayed here</p>
              </div>
              <div class="chart-placeholder">
                <h3>Order Status</h3>
                <p>Order distribution chart will be displayed here</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  function getDashboardCSS() {
    return `
      <style>
        .orders-jet-dashboard {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          margin: 0;
          padding: 20px;
          background: #f5f5f5;
          min-height: 100vh;
        }
        
        .dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 30px;
          padding: 20px;
          background: white;
          border-radius: 8px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .dashboard-header h1 {
          margin: 0;
          color: #333;
          font-size: 28px;
        }
        
        .header-actions {
          display: flex;
          gap: 10px;
        }
        
        .language-toggle, .refresh-btn {
          padding: 8px 16px;
          border: 1px solid #ddd;
          background: white;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
        }
        
        .language-toggle:hover, .refresh-btn:hover {
          background: #f0f0f0;
        }
        
        .dashboard-content {
          display: flex;
          gap: 20px;
        }
        
        .sidebar {
          width: 250px;
          background: white;
          border-radius: 8px;
          padding: 20px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .dashboard-nav {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        
        .nav-item {
          padding: 12px 16px;
          text-decoration: none;
          color: #666;
          border-radius: 4px;
          transition: all 0.2s;
        }
        
        .nav-item:hover {
          background: #f0f0f0;
          color: #333;
        }
        
        .nav-item.active {
          background: #007cba;
          color: white;
        }
        
        .main-content {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 20px;
        }
        
        .metrics-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
          gap: 20px;
        }
        
        .metric-card {
          background: white;
          padding: 20px;
          border-radius: 8px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          display: flex;
          align-items: center;
          gap: 15px;
        }
        
        .metric-icon {
          font-size: 24px;
          width: 50px;
          height: 50px;
          display: flex;
          align-items: center;
          justify-content: center;
          border-radius: 50%;
          background: #f0f0f0;
        }
        
        .metric-content h3 {
          margin: 0 0 8px 0;
          color: #666;
          font-size: 14px;
          font-weight: 500;
        }
        
        .metric-value {
          font-size: 24px;
          font-weight: bold;
          color: #333;
          margin-bottom: 4px;
        }
        
        .metric-change {
          font-size: 12px;
          color: #666;
        }
        
        .revenue .metric-icon { background: #e8f5e8; }
        .tables .metric-icon { background: #fff3cd; }
        .staff .metric-icon { background: #f8d7da; }
        
        .charts-section {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
          gap: 20px;
        }
        
        .chart-placeholder {
          background: white;
          padding: 30px;
          border-radius: 8px;
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
          text-align: center;
          border: 2px dashed #ddd;
        }
        
        .chart-placeholder h3 {
          margin: 0 0 10px 0;
          color: #333;
        }
        
        .chart-placeholder p {
          margin: 0;
          color: #666;
        }
        
        /* RTL Support */
        .orders-jet-dashboard[dir="rtl"] {
          text-align: right;
        }
        
        .orders-jet-dashboard[dir="rtl"] .dashboard-content {
          direction: rtl;
        }
        
        .orders-jet-dashboard[dir="rtl"] .metric-card {
          flex-direction: row-reverse;
        }
      </style>
    `;
  }

  function renderDashboard() {
    const container = document.getElementById('orders-jet-manager-dashboard') || 
                     document.getElementById('orders-jet-kitchen-dashboard') || 
                     document.getElementById('orders-jet-waiter-dashboard');
    
    if (!container) {
      console.error('Dashboard container not found!');
      return;
    }
    
    // Remove existing styles
    const existingStyle = document.getElementById('orders-jet-dashboard-style');
    if (existingStyle) {
      existingStyle.remove();
    }
    
    // Add new styles
    const styleElement = document.createElement('div');
    styleElement.id = 'orders-jet-dashboard-style';
    styleElement.innerHTML = getDashboardCSS();
    document.head.appendChild(styleElement);
    
    // Render dashboard
    container.innerHTML = getDashboardHTML();
    
    // Add event listeners
    addEventListeners();
    
    console.log('Dashboard rendered successfully!');
  }

  function addEventListeners() {
    // Navigation
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('nav-item')) {
        e.preventDefault();
        document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
        e.target.classList.add('active');
        console.log('Navigated to:', e.target.dataset.section);
      }
    });
  }

  // Public API
  window.OrdersJetDashboard = {
    render: renderDashboard,
    toggleLanguage: function() {
      language = language === 'ar' ? 'en' : 'ar';
      renderDashboard();
    },
    refresh: function() {
      console.log('Refreshing dashboard...');
      renderDashboard();
    }
  };

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderDashboard);
  } else {
    renderDashboard();
  }

})();
