# Orders Jet React Dashboard

Modern React-based dashboard for Orders Jet restaurant management system with Arabic/RTL support.

## Features

- **Arabic/RTL Support**: Full Arabic language support with right-to-left layout
- **Role-based Dashboards**: Different interfaces for Manager, Kitchen, and Waiter roles
- **Real-time Updates**: Live data updates using WebSocket connections
- **Modern UI**: Material-UI components with custom Arabic theme
- **Responsive Design**: Works on desktop, tablet, and mobile devices

## Technology Stack

- **React 18** with TypeScript
- **Material-UI** for UI components
- **Recharts** for data visualization
- **Socket.io** for real-time features
- **i18next** for internationalization
- **Axios** for API communication

## Getting Started

### Prerequisites

- Node.js 16+ and npm
- WordPress with Orders Jet Integration plugin

### Installation

1. Install dependencies:
```bash
npm install
```

2. Build the dashboard:
```bash
npm run build
```

3. The built files will be in the `build/` directory and automatically loaded by WordPress.

### Development

1. Start development server:
```bash
npm start
```

2. Open [http://localhost:3000](http://localhost:3000) to view the dashboard.

## Project Structure

```
dashboard/
├── public/
│   ├── index.html          # HTML template with Arabic fonts
│   └── manifest.json       # PWA manifest
├── src/
│   ├── components/
│   │   ├── Layout/         # Dashboard layout components
│   │   ├── Dashboard/      # Role-specific dashboards
│   │   └── Common/         # Shared components
│   ├── hooks/
│   │   └── useAuth.ts      # Authentication hook
│   ├── services/
│   │   └── api.ts          # WordPress REST API service
│   ├── theme/
│   │   └── theme.ts        # Arabic RTL theme configuration
│   ├── i18n/
│   │   ├── index.ts        # Internationalization setup
│   │   └── locales/
│   │       ├── ar.json     # Arabic translations
│   │       └── en.json     # English translations
│   ├── App.tsx             # Main app component
│   └── index.tsx           # App entry point
├── package.json            # Dependencies and scripts
├── tsconfig.json           # TypeScript configuration
└── build.js                # Build script
```

## Dashboard Roles

### Manager Dashboard
- Overview metrics and KPIs
- Real-time table management
- Order tracking and analytics
- Staff management
- Payment and delivery analytics

### Kitchen Dashboard
- Order queue with preparation times
- Order status updates
- Kitchen display system
- Preparation time tracking

### Waiter Dashboard
- Assigned tables management
- Order delivery tracking
- Payment collection
- Invoice generation

## API Integration

The dashboard communicates with WordPress through REST API endpoints:

- `GET /wp-json/orders-jet/v1/dashboard` - Dashboard data
- `GET /wp-json/orders-jet/v1/tables` - Tables data
- `POST /wp-json/orders-jet/v1/tables/{id}/status` - Update table status
- `GET /wp-json/orders-jet/v1/orders` - Orders data
- `POST /wp-json/orders-jet/v1/orders/{id}/status` - Update order status

## Arabic Localization

The dashboard includes comprehensive Arabic translations:

- All UI text and labels
- Date and time formatting
- Currency formatting (Egyptian Pound)
- RTL layout support
- Arabic fonts (Cairo, Tajawal)

## Building for Production

```bash
npm run build
```

This creates optimized production builds in the `build/` directory.

## Contributing

1. Follow TypeScript best practices
2. Use Arabic translations for all user-facing text
3. Ensure RTL layout compatibility
4. Test on different screen sizes
5. Follow Material-UI design guidelines

## License

GPL v2 or later - Same as Orders Jet Integration plugin
