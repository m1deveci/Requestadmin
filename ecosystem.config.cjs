module.exports = {
  apps: [
    {
      name: 'requestadmin-api',
      script: 'server/server.js',
      cwd: '/var/www/requestadmin',
      instances: 1,
      exec_mode: 'fork',
      env: {
        NODE_ENV: 'production',
        PORT: 3008
      },
      env_production: {
        NODE_ENV: 'production',
        PORT: 3008
      },
      // Logging
      log_file: '/var/log/pm2/requestadmin-api.log',
      out_file: '/var/log/pm2/requestadmin-api-out.log',
      error_file: '/var/log/pm2/requestadmin-api-error.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      
      // Auto restart
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      
      // Health monitoring
      min_uptime: '10s',
      max_restarts: 10,
      
      // Graceful shutdown
      kill_timeout: 5000,
      listen_timeout: 3000,
      
      // Environment variables
      env_file: '/var/www/requestadmin/config.env'
    }
  ],

  deploy: {
    production: {
      user: 'root',
      host: 'localhost',
      ref: 'origin/main',
      repo: 'https://github.com/m1deveci/Requestadmin.git',
      path: '/var/www/requestadmin',
      'pre-deploy-local': '',
      'post-deploy': 'npm install && npm run build && pm2 reload ecosystem.config.js --env production',
      'pre-setup': ''
    }
  }
};


