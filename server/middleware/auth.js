import jwt from 'jsonwebtoken';
import pool from '../config/database.js';

export const authenticateToken = async (req, res, next) => {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1]; // Bearer TOKEN

  if (!token) {
    return res.status(401).json({ message: 'Erişim token\'ı gerekli' });
  }

  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your-secret-key');
    
    // Kullanıcıyı veritabanından kontrol et
    const [users] = await pool.execute(
      'SELECT id, email, role, status, company_id FROM users WHERE id = ? AND status = "active"',
      [decoded.userId]
    );

    if (users.length === 0) {
      return res.status(401).json({ message: 'Geçersiz token' });
    }

    req.user = users[0];
    next();
  } catch (error) {
    console.error('Token doğrulama hatası:', error);
    return res.status(403).json({ message: 'Geçersiz token' });
  }
};

export const requireRole = (roles) => {
  return (req, res, next) => {
    if (!req.user) {
      return res.status(401).json({ message: 'Kimlik doğrulama gerekli' });
    }

    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ message: 'Bu işlem için yetkiniz yok' });
    }

    next();
  };
};

export const requireAdmin = requireRole(['admin']);
export const requireHR = requireRole(['admin', 'hr']);
export const requireEmployee = requireRole(['admin', 'hr', 'employee']);


