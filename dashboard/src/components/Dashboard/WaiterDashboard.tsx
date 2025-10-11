import React from 'react';
import { Box, Typography, Card, CardContent } from '@mui/material';
import { useTranslation } from 'react-i18next';

const WaiterDashboard: React.FC = () => {
  const { t } = useTranslation();

  return (
    <Box>
      <Typography variant="h4" sx={{ fontWeight: 700, color: 'primary.main', mb: 3 }}>
        {t('dashboard.waiter')}
      </Typography>
      
      <Card>
        <CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>
            لوحة تحكم النادل
          </Typography>
          <Typography variant="body1" color="text.secondary">
            ستتم إضافة مكونات لوحة تحكم النادل هنا، بما في ذلك:
          </Typography>
          <ul style={{ marginTop: 16, paddingRight: 20 }}>
            <li>إدارة الطاولات المسؤول عنها</li>
            <li>توصيل الطلبات الجاهزة</li>
            <li>جمع المدفوعات</li>
            <li>إنشاء الفواتير</li>
          </ul>
        </CardContent>
      </Card>
    </Box>
  );
};

export default WaiterDashboard;
