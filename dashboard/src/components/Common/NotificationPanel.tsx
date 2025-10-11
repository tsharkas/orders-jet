import React, { useState, useEffect } from 'react';
import {
  Drawer,
  Box,
  Typography,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  IconButton,
  Divider,
  Chip,
  Badge,
} from '@mui/material';
import {
  Close as CloseIcon,
  Notifications as NotificationIcon,
  Receipt as OrderIcon,
  TableRestaurant as TableIcon,
  Payment as PaymentIcon,
  Warning as WarningIcon,
  CheckCircle as SuccessIcon,
  Info as InfoIcon,
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';

interface Notification {
  id: string;
  type: 'order' | 'table' | 'payment' | 'warning' | 'success' | 'info';
  title: string;
  message: string;
  timestamp: Date;
  read: boolean;
  priority: 'low' | 'medium' | 'high';
}

interface NotificationPanelProps {
  open: boolean;
  onClose: () => void;
}

const NotificationPanel: React.FC<NotificationPanelProps> = ({ open, onClose }) => {
  const { t } = useTranslation();
  const [notifications, setNotifications] = useState<Notification[]>([]);

  useEffect(() => {
    // Mock notifications - in real app, these would come from API
    const mockNotifications: Notification[] = [
      {
        id: '1',
        type: 'order',
        title: t('notifications.newOrder'),
        message: 'طلب جديد من الطاولة T05',
        timestamp: new Date(Date.now() - 5 * 60 * 1000), // 5 minutes ago
        read: false,
        priority: 'high',
      },
      {
        id: '2',
        type: 'table',
        title: t('notifications.tableClaimed'),
        message: 'تم استلام الطاولة T03 بواسطة أحمد',
        timestamp: new Date(Date.now() - 15 * 60 * 1000), // 15 minutes ago
        read: false,
        priority: 'medium',
      },
      {
        id: '3',
        type: 'payment',
        title: t('notifications.paymentRequired'),
        message: 'طلب دفع للطاولة T02 - 250 ج.م',
        timestamp: new Date(Date.now() - 30 * 60 * 1000), // 30 minutes ago
        read: true,
        priority: 'high',
      },
      {
        id: '4',
        type: 'success',
        title: 'تم بنجاح',
        message: 'تم تحديث حالة الطلب #1234',
        timestamp: new Date(Date.now() - 45 * 60 * 1000), // 45 minutes ago
        read: true,
        priority: 'low',
      },
    ];

    setNotifications(mockNotifications);
  }, [t]);

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'order':
        return <OrderIcon color="primary" />;
      case 'table':
        return <TableIcon color="secondary" />;
      case 'payment':
        return <PaymentIcon color="warning" />;
      case 'warning':
        return <WarningIcon color="warning" />;
      case 'success':
        return <SuccessIcon color="success" />;
      case 'info':
        return <InfoIcon color="info" />;
      default:
        return <NotificationIcon />;
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'high':
        return 'error';
      case 'medium':
        return 'warning';
      case 'low':
        return 'info';
      default:
        return 'default';
    }
  };

  const formatTimestamp = (timestamp: Date) => {
    const now = new Date();
    const diff = now.getTime() - timestamp.getTime();
    const minutes = Math.floor(diff / (1000 * 60));
    const hours = Math.floor(diff / (1000 * 60 * 60));

    if (minutes < 1) {
      return t('time.now');
    } else if (minutes < 60) {
      return t('time.minutesAgo', { count: minutes });
    } else {
      return t('time.hoursAgo', { count: hours });
    }
  };

  const unreadCount = notifications.filter(n => !n.read).length;

  return (
    <Drawer
      anchor="right"
      open={open}
      onClose={onClose}
      sx={{
        '& .MuiDrawer-paper': {
          width: 400,
          maxWidth: '90vw',
        },
      }}
    >
      <Box sx={{ p: 2 }}>
        {/* Header */}
        <Box
          sx={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            mb: 2,
          }}
        >
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
            <NotificationIcon color="primary" />
            <Typography variant="h6" sx={{ fontWeight: 600 }}>
              الإشعارات
            </Typography>
            {unreadCount > 0 && (
              <Chip
                label={unreadCount}
                size="small"
                color="error"
                variant="filled"
              />
            )}
          </Box>
          <IconButton onClick={onClose} size="small">
            <CloseIcon />
          </IconButton>
        </Box>

        <Divider sx={{ mb: 2 }} />

        {/* Notifications List */}
        <List sx={{ p: 0 }}>
          {notifications.length === 0 ? (
            <ListItem>
              <ListItemText
                primary="لا توجد إشعارات"
                secondary="ستظهر الإشعارات الجديدة هنا"
                sx={{ textAlign: 'center' }}
              />
            </ListItem>
          ) : (
            notifications.map((notification, index) => (
              <React.Fragment key={notification.id}>
                <ListItem
                  sx={{
                    backgroundColor: notification.read ? 'transparent' : 'action.hover',
                    borderRadius: 2,
                    mb: 1,
                    border: notification.read ? 'none' : '1px solid',
                    borderColor: 'primary.light',
                  }}
                >
                  <ListItemIcon sx={{ minWidth: 40 }}>
                    {getNotificationIcon(notification.type)}
                  </ListItemIcon>
                  <ListItemText
                    primary={
                      <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        <Typography
                          variant="subtitle2"
                          sx={{
                            fontWeight: notification.read ? 400 : 600,
                          }}
                        >
                          {notification.title}
                        </Typography>
                        <Chip
                          label={notification.priority}
                          size="small"
                          color={getPriorityColor(notification.priority) as any}
                          variant="outlined"
                        />
                      </Box>
                    }
                    secondary={
                      <Box>
                        <Typography variant="body2" sx={{ mb: 0.5 }}>
                          {notification.message}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                          {formatTimestamp(notification.timestamp)}
                        </Typography>
                      </Box>
                    }
                  />
                </ListItem>
                {index < notifications.length - 1 && (
                  <Divider variant="middle" />
                )}
              </React.Fragment>
            ))
          )}
        </List>
      </Box>
    </Drawer>
  );
};

export default NotificationPanel;
