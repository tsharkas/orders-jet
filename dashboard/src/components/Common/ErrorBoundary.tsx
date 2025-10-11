import React, { Component, ErrorInfo, ReactNode } from 'react';
import { Box, Typography, Button, Paper } from '@mui/material';
import { Error as ErrorIcon, Refresh as RefreshIcon } from '@mui/icons-material';
import { useTranslation } from 'react-i18next';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error?: Error;
}

class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    console.error('Orders Jet Dashboard Error:', error, errorInfo);
  }

  public render() {
    if (this.state.hasError) {
      return (
        <Box
          sx={{
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            minHeight: '100vh',
            p: 3,
            backgroundColor: 'background.default',
          }}
        >
          <Paper
            elevation={3}
            sx={{
              p: 4,
              maxWidth: 500,
              textAlign: 'center',
              borderRadius: 3,
            }}
          >
            <ErrorIcon
              sx={{
                fontSize: 64,
                color: 'error.main',
                mb: 2,
              }}
            />
            
            <Typography
              variant="h5"
              sx={{
                mb: 2,
                fontWeight: 600,
                color: 'error.main',
              }}
            >
              خطأ في التطبيق
            </Typography>
            
            <Typography
              variant="body1"
              sx={{
                mb: 3,
                color: 'text.secondary',
              }}
            >
              حدث خطأ غير متوقع. يرجى إعادة تحميل الصفحة أو المحاولة مرة أخرى.
            </Typography>

            {this.state.error && (
              <Typography
                variant="body2"
                sx={{
                  mb: 3,
                  color: 'text.secondary',
                  fontFamily: 'monospace',
                  backgroundColor: 'background.paper',
                  p: 2,
                  borderRadius: 1,
                  border: '1px solid',
                  borderColor: 'divider',
                }}
              >
                {this.state.error.message}
              </Typography>
            )}

            <Button
              variant="contained"
              startIcon={<RefreshIcon />}
              onClick={() => window.location.reload()}
              sx={{
                borderRadius: 2,
                textTransform: 'none',
                px: 3,
                py: 1,
              }}
            >
              إعادة تحميل الصفحة
            </Button>
          </Paper>
        </Box>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
