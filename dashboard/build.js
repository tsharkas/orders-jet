const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

console.log('🚀 Building Orders Jet Dashboard...');

try {
  // Install dependencies if node_modules doesn't exist
  if (!fs.existsSync('node_modules')) {
    console.log('📦 Installing dependencies...');
    execSync('npm install', { stdio: 'inherit' });
  }

  // Build the React app
  console.log('🔨 Building React app...');
  execSync('npm run build', { stdio: 'inherit' });

  console.log('✅ Build completed successfully!');
  console.log('📁 Built files are in the "build" directory');
  
} catch (error) {
  console.error('❌ Build failed:', error.message);
  process.exit(1);
}
