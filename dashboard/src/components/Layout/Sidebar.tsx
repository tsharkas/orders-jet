import React from 'react';
import {
  Box,
  List,
  ListItem,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Divider,
  Typography,
  Avatar,
  Chip,
} from '@mui/material';
import {
  Dashboard as DashboardIcon,
  TableRestaurant as TableIcon,
  Receipt as OrderIcon,
  People as StaffIcon,
  Analytics as AnalyticsIcon,
  Settings as SettingsIcon,
  Help as HelpIcon,
  Language as LanguageIcon,
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../hooks/useAuth';

interface SidebarProps {
  onLanguageChange: (language: string) => void;
  currentLanguage: string;
}

interface NavItem {
  id: string;
  label: string;
  icon: React.ReactElement;
  path: string;
  badge?: number;
  roles?: string[];
}

const Sidebar: React.FC<SidebarProps> = ({ onLanguageChange, currentLanguage }) => {
  const { t } = useTranslation();
  const { user } = useAuth();

  const navigationItems: NavItem[] = [
    {
      id: 'overview',
      label: t('navigation.overview'),
      icon: <DashboardIcon />,
      path: '/overview',
      roles: ['oj_manager', 'oj_kitchen', 'oj_waiter'],
    },
    {
      id: 'tables',
      label: t('navigation.tables'),
      icon: <TableIcon />,
      path: '/tables',
      badge: 3, // Number of occupied tables
      roles: ['oj_manager', 'oj_waiter'],
    },
    {
      id: 'orders',
      label: t('navigation.orders'),
      icon: <OrderIcon />,
      path: '/orders',
      badge: 5, // Number of pending orders
      roles: ['oj_manager', 'oj_kitchen', 'oj_waiter'],
    },
    {
      id: 'staff',
      label: t('navigation.staff'),
      icon: <StaffIcon />,
      path: '/staff',
      roles: ['oj_manager'],
    },
    {
      id: 'analytics',
      label: t('navigation.analytics'),
      icon: <AnalyticsIcon />,
      path: '/analytics',
      roles: ['oj_manager'],
    },
    {
      id: 'settings',
      label: t('navigation.settings'),
      icon: <SettingsIcon />,
      path: '/settings',
      roles: ['oj_manager'],
    },
  ];

  const handleLanguageChange = () => {
    const newLanguage = currentLanguage === 'ar' ? 'en' : 'ar';
    onLanguageChange(newLanguage);
  };

  const filteredItems = navigationItems.filter(item => 
    !item.roles || item.roles.includes(user?.role || '')
  );

  const getRoleDisplayName = (role: string) => {
    const roleNames = {
      oj_manager: t('staff.manager'),
      oj_kitchen: t('staff.kitchen'),
      oj_waiter: t('staff.waiter'),
    };
    return roleNames[role as keyof typeof roleNames] || role;
  };

  return (
    <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
      {/* Logo and Title */}
      <Box
        sx={{
          p: 2,
          display: 'flex',
          alignItems: 'center',
          borderBottom: '1px solid',
          borderColor: 'divider',
        }}
      >
        <Avatar
          sx={{
            bgcolor: 'primary.main',
            width: 40,
            height: 40,
            mr: 2,
          }}
        >
          🍽️
        </Avatar>
        <Box>
          <Typography variant="h6" sx={{ fontWeight: 600, color: 'primary.main' }}>
            شَهْبَنْدَر
          </Typography>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {getRoleDisplayName(user?.role || '')}
          </Typography>
        </Box>
      </Box>

      {/* User Info */}
      <Box
        sx={{
          p: 2,
          borderBottom: '1px solid',
          borderColor: 'divider',
          backgroundColor: 'background.paper',
        }}
      >
        <Typography variant="body2" sx={{ color: 'text.secondary', mb: 1 }}>
          مرحباً، {user?.name}
        </Typography>
        <Chip
          label={getRoleDisplayName(user?.role || '')}
          size="small"
          color="primary"
          variant="outlined"
        />
      </Box>

      {/* Navigation Items */}
      <List sx={{ flexGrow: 1, py: 1 }}>
        {filteredItems.map((item) => (
          <ListItem key={item.id} disablePadding>
            <ListItemButton
              sx={{
                mx: 1,
                borderRadius: 2,
                '&.Mui-selected': {
                  backgroundColor: 'primary.main',
                  color: 'primary.contrastText',
                  '& .MuiListItemIcon-root': {
                    color: 'primary.contrastText',
                  },
                  '&:hover': {
                    backgroundColor: 'primary.dark',
                  },
                },
              }}
            >
              <ListItemIcon sx={{ color: 'text.secondary' }}>
                {item.icon}
              </ListItemIcon>
              <ListItemText
                primary={item.label}
                sx={{
                  '& .MuiListItemText-primary': {
                    fontWeight: 500,
                  },
                }}
              />
              {item.badge && (
                <Chip
                  label={item.badge}
                  size="small"
                  color="error"
                  sx={{ ml: 1 }}
                />
              )}
            </ListItemButton>
          </ListItem>
        ))}
      </List>

      <Divider />

      {/* Language Toggle */}
      <List>
        <ListItem disablePadding>
          <ListItemButton
            onClick={handleLanguageChange}
            sx={{
              mx: 1,
              borderRadius: 2,
              mb: 1,
            }}
          >
            <ListItemIcon>
              <LanguageIcon />
            </ListItemIcon>
            <ListItemText
              primary={currentLanguage === 'ar' ? 'English' : 'العربية'}
              secondary={currentLanguage === 'ar' ? 'Switch to English' : 'التبديل للعربية'}
            />
          </ListItemButton>
        </ListItem>
      </List>

      {/* Help */}
      <List>
        <ListItem disablePadding>
          <ListItemButton
            sx={{
              mx: 1,
              borderRadius: 2,
              mb: 2,
            }}
          >
            <ListItemIcon>
              <HelpIcon />
            </ListItemIcon>
            <ListItemText primary={t('navigation.help')} />
          </ListItemButton>
        </ListItem>
      </List>
    </Box>
  );
};

export default Sidebar;
