import React from 'react';
import { Box, Typography, Card, CardContent } from '@mui/material';
import { useTranslation } from 'react-i18next';

const KitchenDashboard: React.FC = () => {
  const { t } = useTranslation();

  return (
    <Box>
      <Typography variant="h4" sx={{ fontWeight: 700, color: 'primary.main', mb: 3 }}>
        {t('dashboard.kitchen')}
      </Typography>
      
      <Card>
        <CardContent>
          <Typography variant="h6" sx={{ mb: 2 }}>
            لوحة تحكم المطبخ
          </Typography>
          <Typography variant="body1" color="text.secondary">
            ستتم إضافة مكونات لوحة تحكم المطبخ هنا، بما في ذلك:
          </Typography>
          <ul style={{ marginTop: 16, paddingRight: 20 }}>
            <li>قائمة انتظار الطلبات</li>
            <li>تتبع وقت التحضير</li>
            <li>تحديث حالة الطلبات</li>
            <li>إشعارات الطلبات الجديدة</li>
          </ul>
        </CardContent>
      </Card>
    </Box>
  );
};

export default KitchenDashboard;
