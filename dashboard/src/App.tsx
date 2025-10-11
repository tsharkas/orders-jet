import React, { useState, useEffect } from 'react';
import { ThemeProvider } from '@mui/material/styles';
import { CssBaseline, Box } from '@mui/material';
import { CacheProvider } from '@emotion/react';
import { useTranslation } from 'react-i18next';
import { arabicTheme, englishTheme, cacheRtl } from './theme/theme';
import DashboardLayout from './components/Layout/DashboardLayout';
import ManagerDashboard from './components/Dashboard/ManagerDashboard';
import KitchenDashboard from './components/Dashboard/KitchenDashboard';
import WaiterDashboard from './components/Dashboard/WaiterDashboard';
import LoadingScreen from './components/Common/LoadingScreen';
import ErrorBoundary from './components/Common/ErrorBoundary';
import { useAuth } from './hooks/useAuth';
import './i18n';

// Global configuration from WordPress
declare global {
  interface Window {
    OrdersJetConfig: {
      apiUrl: string;
      nonce: string;
      userRole: string;
      userId: number;
      userName: string;
      siteUrl: string;
      pluginUrl: string;
      websocketUrl: string;
    };
  }
}

function App() {
  const { i18n } = useTranslation();
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const { user, loading, error: authError } = useAuth();

  useEffect(() => {
    // Initialize app
    const initializeApp = async () => {
      try {
        // Check if we have WordPress configuration
        if (!window.OrdersJetConfig) {
          throw new Error('Orders Jet configuration not found');
        }

        // Set language based on user preference or default to Arabic
        const savedLanguage = localStorage.getItem('orders-jet-language') || 'ar';
        await i18n.changeLanguage(savedLanguage);

        setIsLoading(false);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to initialize app');
        setIsLoading(false);
      }
    };

    initializeApp();
  }, [i18n]);

  const handleLanguageChange = async (language: string) => {
    await i18n.changeLanguage(language);
    localStorage.setItem('orders-jet-language', language);
  };

  // Get theme based on current language
  const currentTheme = i18n.language === 'ar' ? arabicTheme : englishTheme;
  const currentCache = i18n.language === 'ar' ? cacheRtl : undefined;

  if (isLoading) {
    return <LoadingScreen />;
  }

  if (error || authError) {
    return (
      <Box
        sx={{
          display: 'flex',
          justifyContent: 'center',
          alignItems: 'center',
          height: '100vh',
          flexDirection: 'column',
        }}
      >
        <h1>خطأ في التطبيق</h1>
        <p>{error || authError}</p>
      </Box>
    );
  }

  const renderDashboard = () => {
    if (!user) return <LoadingScreen />;

    switch (user.role) {
      case 'oj_manager':
        return <ManagerDashboard />;
      case 'oj_kitchen':
        return <KitchenDashboard />;
      case 'oj_waiter':
        return <WaiterDashboard />;
      default:
        return <LoadingScreen />;
    }
  };

  return (
    <ErrorBoundary>
      <CacheProvider value={currentCache}>
        <ThemeProvider theme={currentTheme}>
          <CssBaseline />
          <DashboardLayout
            onLanguageChange={handleLanguageChange}
            currentLanguage={i18n.language}
          >
            {renderDashboard()}
          </DashboardLayout>
        </ThemeProvider>
      </CacheProvider>
    </ErrorBoundary>
  );
}

export default App;
