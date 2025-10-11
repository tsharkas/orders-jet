import React, { useState, useEffect } from 'react';
import {
  Box,
  Grid,
  Typography,
  Paper,
  Card,
  CardContent,
  CardHeader,
  IconButton,
  Chip,
  Avatar,
  LinearProgress,
  Alert,
} from '@mui/material';
import {
  Refresh as RefreshIcon,
  TrendingUp as TrendingUpIcon,
  TrendingDown as TrendingDownIcon,
  Restaurant as RestaurantIcon,
  People as PeopleIcon,
  AttachMoney as MoneyIcon,
  AccessTime as TimeIcon,
  ShoppingCart as CartIcon,
  TableRestaurant as TableIcon,
} from '@mui/icons-material';
import { useTranslation } from 'react-i18next';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, BarChart, Bar, PieChart, Pie, Cell } from 'recharts';
import { apiService } from '../../services/api';
import { useAuth } from '../../hooks/useAuth';

interface DashboardMetrics {
  todayOrders: number;
  todayRevenue: number;
  occupiedTables: number;
  staffActivity: number;
  avgOrderTime: number;
  totalCustomers: number;
  completedOrders: number;
  pendingOrders: number;
}

interface TableData {
  id: number;
  number: string;
  status: string;
  capacity: number;
  assignedWaiter: string;
  sessionStart: string;
  sessionTotal: number;
}

interface OrderData {
  id: number;
  orderNumber: string;
  tableNumber: string;
  status: string;
  total: number;
  date: string;
  items: number;
}

const ManagerDashboard: React.FC = () => {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
  const [tables, setTables] = useState<TableData[]>([]);
  const [orders, setOrders] = useState<OrderData[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);

      // Fetch dashboard data from API
      const dashboardData = await apiService.getDashboardData();
      
      setMetrics(dashboardData.metrics);
      setTables(dashboardData.tables);
      setOrders(dashboardData.orders);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch dashboard data');
    } finally {
      setLoading(false);
    }
  };

  const handleRefresh = () => {
    fetchDashboardData();
  };

  // Mock data for charts (will be replaced with real data)
  const revenueData = [
    { name: '08:00', revenue: 1200 },
    { name: '10:00', revenue: 1900 },
    { name: '12:00', revenue: 3000 },
    { name: '14:00', revenue: 2800 },
    { name: '16:00', revenue: 1890 },
    { name: '18:00', revenue: 2390 },
    { name: '20:00', revenue: 3490 },
  ];

  const orderStatusData = [
    { name: 'مكتمل', value: 45, color: '#4caf50' },
    { name: 'قيد التحضير', value: 25, color: '#ff9800' },
    { name: 'معلق', value: 15, color: '#f44336' },
    { name: 'جاهز', value: 15, color: '#2196f3' },
  ];

  const paymentMethodData = [
    { name: 'فواتيرك', value: 40, color: '#1976d2' },
    { name: 'إنستاباي', value: 25, color: '#dc004e' },
    { name: 'والد', value: 20, color: '#2e7d32' },
    { name: 'نقدي', value: 15, color: '#ff9800' },
  ];

  if (loading) {
    return (
      <Box>
        <LinearProgress />
        <Typography sx={{ mt: 2, textAlign: 'center' }}>
          {t('common.loading')}
        </Typography>
      </Box>
    );
  }

  if (error) {
    return (
      <Alert severity="error" sx={{ mb: 2 }}>
        {error}
      </Alert>
    );
  }

  return (
    <Box>
      {/* Header */}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 3 }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 700, color: 'primary.main', mb: 1 }}>
            {t('dashboard.manager')}
          </Typography>
          <Typography variant="body1" color="text.secondary">
            نظرة عامة على أداء المطعم اليوم
          </Typography>
        </Box>
        <IconButton
          onClick={handleRefresh}
          sx={{
            backgroundColor: 'primary.main',
            color: 'white',
            '&:hover': {
              backgroundColor: 'primary.dark',
            },
          }}
        >
          <RefreshIcon />
        </IconButton>
      </Box>

      {/* Metrics Cards */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        <Grid item xs={12} sm={6} md={3}>
          <Card sx={{ height: '100%', position: 'relative', overflow: 'visible' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="text.secondary" gutterBottom variant="body2">
                    {t('metrics.todayOrders')}
                  </Typography>
                  <Typography variant="h4" sx={{ fontWeight: 700, color: 'primary.main' }}>
                    {metrics?.todayOrders || 0}
                  </Typography>
                  <Box sx={{ display: 'flex', alignItems: 'center', mt: 1 }}>
                    <TrendingUpIcon sx={{ color: 'success.main', fontSize: 16, mr: 0.5 }} />
                    <Typography variant="body2" color="success.main">
                      +12% من أمس
                    </Typography>
                  </Box>
                </Box>
                <Avatar sx={{ bgcolor: 'primary.main', width: 56, height: 56 }}>
                  <CartIcon />
                </Avatar>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="text.secondary" gutterBottom variant="body2">
                    {t('metrics.todayRevenue')}
                  </Typography>
                  <Typography variant="h4" sx={{ fontWeight: 700, color: 'success.main' }}>
                    {metrics?.todayRevenue || 0} ج.م
                  </Typography>
                  <Box sx={{ display: 'flex', alignItems: 'center', mt: 1 }}>
                    <TrendingUpIcon sx={{ color: 'success.main', fontSize: 16, mr: 0.5 }} />
                    <Typography variant="body2" color="success.main">
                      +8% من أمس
                    </Typography>
                  </Box>
                </Box>
                <Avatar sx={{ bgcolor: 'success.main', width: 56, height: 56 }}>
                  <MoneyIcon />
                </Avatar>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="text.secondary" gutterBottom variant="body2">
                    {t('metrics.occupiedTables')}
                  </Typography>
                  <Typography variant="h4" sx={{ fontWeight: 700, color: 'warning.main' }}>
                    {metrics?.occupiedTables || 0}
                  </Typography>
                  <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    من أصل 15 طاولة
                  </Typography>
                </Box>
                <Avatar sx={{ bgcolor: 'warning.main', width: 56, height: 56 }}>
                  <TableIcon />
                </Avatar>
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card sx={{ height: '100%' }}>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="text.secondary" gutterBottom variant="body2">
                    {t('metrics.staffActivity')}
                  </Typography>
                  <Typography variant="h4" sx={{ fontWeight: 700, color: 'secondary.main' }}>
                    {metrics?.staffActivity || 0}%
                  </Typography>
                  <Box sx={{ display: 'flex', alignItems: 'center', mt: 1 }}>
                    <PeopleIcon sx={{ color: 'secondary.main', fontSize: 16, mr: 0.5 }} />
                    <Typography variant="body2" color="text.secondary">
                      12 موظف نشط
                    </Typography>
                  </Box>
                </Box>
                <Avatar sx={{ bgcolor: 'secondary.main', width: 56, height: 56 }}>
                  <PeopleIcon />
                </Avatar>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Charts Row */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        {/* Revenue Chart */}
        <Grid item xs={12} md={8}>
          <Card>
            <CardHeader
              title="الإيرادات اليومية"
              subheader="تتبع الإيرادات على مدار اليوم"
              action={
                <Chip label="اليوم" color="primary" variant="outlined" />
              }
            />
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={revenueData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip formatter={(value) => [`${value} ج.م`, 'الإيرادات']} />
                  <Line 
                    type="monotone" 
                    dataKey="revenue" 
                    stroke="#1976d2" 
                    strokeWidth={3}
                    dot={{ fill: '#1976d2', strokeWidth: 2, r: 6 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* Order Status Pie Chart */}
        <Grid item xs={12} md={4}>
          <Card>
            <CardHeader
              title="حالة الطلبات"
              subheader="توزيع الطلبات حسب الحالة"
            />
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={orderStatusData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={100}
                    dataKey="value"
                  >
                    {orderStatusData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip formatter={(value) => [`${value} طلب`, 'العدد']} />
                </PieChart>
              </ResponsiveContainer>
              <Box sx={{ mt: 2 }}>
                {orderStatusData.map((item, index) => (
                  <Box key={index} sx={{ display: 'flex', alignItems: 'center', mb: 1 }}>
                    <Box
                      sx={{
                        width: 12,
                        height: 12,
                        backgroundColor: item.color,
                        borderRadius: '50%',
                        mr: 1,
                      }}
                    />
                    <Typography variant="body2" sx={{ flexGrow: 1 }}>
                      {item.name}
                    </Typography>
                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                      {item.value}%
                    </Typography>
                  </Box>
                ))}
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Payment Methods and Recent Orders */}
      <Grid container spacing={3}>
        {/* Payment Methods */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardHeader
              title="طرق الدفع"
              subheader="توزيع المبيعات حسب طريقة الدفع"
            />
            <CardContent>
              <ResponsiveContainer width="100%" height={250}>
                <BarChart data={paymentMethodData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip formatter={(value) => [`${value}%`, 'النسبة']} />
                  <Bar dataKey="value" fill="#1976d2" />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* Recent Orders */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardHeader
              title="الطلبات الأخيرة"
              subheader="آخر 5 طلبات"
              action={
                <IconButton size="small">
                  <RefreshIcon />
                </IconButton>
              }
            />
            <CardContent>
              {orders.slice(0, 5).map((order) => (
                <Box
                  key={order.id}
                  sx={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    py: 2,
                    borderBottom: '1px solid',
                    borderColor: 'divider',
                    '&:last-child': {
                      borderBottom: 'none',
                    },
                  }}
                >
                  <Box>
                    <Typography variant="subtitle2" sx={{ fontWeight: 600 }}>
                      {order.orderNumber}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      الطاولة {order.tableNumber} • {order.items} منتج
                    </Typography>
                  </Box>
                  <Box sx={{ textAlign: 'left' }}>
                    <Typography variant="subtitle2" sx={{ fontWeight: 600, color: 'primary.main' }}>
                      {order.total} ج.م
                    </Typography>
                    <Chip
                      label={order.status}
                      size="small"
                      color={
                        order.status === 'مكتمل' ? 'success' :
                        order.status === 'قيد التحضير' ? 'warning' :
                        order.status === 'جاهز' ? 'info' : 'default'
                      }
                      variant="outlined"
                    />
                  </Box>
                </Box>
              ))}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
};

export default ManagerDashboard;
