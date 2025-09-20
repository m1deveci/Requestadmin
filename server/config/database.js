import mysql from 'mysql2/promise';
import dotenv from 'dotenv';

dotenv.config({ path: '/var/www/requestadmin/config.env' });

const dbConfig = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'ittoolbox',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'requestadmin',
  port: process.env.DB_PORT || 3306,
  charset: 'utf8mb4',
  timezone: '+00:00',
  acquireTimeout: 60000,
  timeout: 60000,
  reconnect: true
};

// Connection pool oluştur
const pool = mysql.createPool({
  ...dbConfig,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
});

// Test connection
const testConnection = async () => {
  try {
    const connection = await pool.getConnection();
    console.log('MySQL veritabanına başarıyla bağlanıldı');
    connection.release();
  } catch (error) {
    console.error('MySQL bağlantı hatası:', error.message);
    process.exit(1);
  }
};

// Veritabanı bağlantısını test et
testConnection();

export default pool;


