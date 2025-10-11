import React from 'react';
import { Box, CircularProgress, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';

const LoadingScreen: React.FC = () => {
  const { t } = useTranslation();

  return (
    <Box
      sx={{
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
        height: '100vh',
        backgroundColor: 'background.default',
      }}
    >
      <CircularProgress size={60} sx={{ mb: 3, color: 'primary.main' }} />
      <Typography
        variant="h6"
        sx={{
          color: 'text.secondary',
          fontWeight: 500,
        }}
      >
        {t('common.loading')}
      </Typography>
      <Typography
        variant="body2"
        sx={{
          color: 'text.secondary',
          mt: 1,
        }}
      >
        Orders Jet Dashboard
      </Typography>
    </Box>
  );
};

export default LoadingScreen;
