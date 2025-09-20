import express from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { authenticateToken } from '../middleware/auth.js';

const router = express.Router();

// Login
router.post('/login', [
  body('email').isEmail().normalizeEmail(),
  body('password').isLength({ min: 6 })
], async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz giriş bilgileri',
        errors: errors.array()
      });
    }

    const { email, password } = req.body;

    // Kullanıcıyı bul
    const [users] = await pool.execute(
      'SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.email = ? AND u.status = "active"',
      [email]
    );

    if (users.length === 0) {
      return res.status(401).json({ message: 'Geçersiz email veya şifre' });
    }

    const user = users[0];

    // Şifreyi kontrol et
    const isValidPassword = await bcrypt.compare(password, user.password_hash);
    if (!isValidPassword) {
      return res.status(401).json({ message: 'Geçersiz email veya şifre' });
    }

    // JWT token oluştur
    const token = jwt.sign(
      { 
        userId: user.id,
        email: user.email,
        role: user.role,
        companyId: user.company_id
      },
      process.env.JWT_SECRET || 'your-secret-key',
      { expiresIn: '24h' }
    );

    // Son giriş zamanını güncelle
    await pool.execute(
      'UPDATE users SET last_login = NOW() WHERE id = ?',
      [user.id]
    );

    res.json({
      message: 'Giriş başarılı',
      token,
      user: {
        id: user.id,
        email: user.email,
        firstName: user.first_name,
        lastName: user.last_name,
        role: user.role,
        companyId: user.company_id,
        companyName: user.company_name,
        title: user.title,
        department: user.department
      }
    });
  } catch (error) {
    console.error('Login hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Register (Şirket kaydı)
router.post('/register', [
  body('companyName').isLength({ min: 2 }).trim(),
  body('email').isEmail().normalizeEmail(),
  body('phone').isLength({ min: 10 }),
  body('authorizedPerson').isLength({ min: 2 }).trim(),
  body('taxNumber').isLength({ min: 10 }),
  body('address').isLength({ min: 10 }).trim(),
  body('firstName').isLength({ min: 2 }).trim(),
  body('lastName').isLength({ min: 2 }).trim(),
  body('password').isLength({ min: 6 })
], async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz kayıt bilgileri',
        errors: errors.array()
      });
    }

    const {
      companyName,
      email,
      phone,
      authorizedPerson,
      taxNumber,
      address,
      firstName,
      lastName,
      password
    } = req.body;

    // Email kontrolü
    const [existingUsers] = await pool.execute(
      'SELECT id FROM users WHERE email = ?',
      [email]
    );

    if (existingUsers.length > 0) {
      return res.status(400).json({ message: 'Bu email adresi zaten kullanılıyor' });
    }

    // Şirket email kontrolü
    const [existingCompanies] = await pool.execute(
      'SELECT id FROM companies WHERE email = ?',
      [email]
    );

    if (existingCompanies.length > 0) {
      return res.status(400).json({ message: 'Bu email adresi zaten kullanılıyor' });
    }

    // Şirket oluştur
    const [companyResult] = await pool.execute(
      'INSERT INTO companies (company_name, email, phone, authorized_person, tax_number, address, status) VALUES (?, ?, ?, ?, ?, ?, "pending")',
      [companyName, email, phone, authorizedPerson, taxNumber, address]
    );

    const companyId = companyResult.insertId;

    // Şifreyi hashle
    const hashedPassword = await bcrypt.hash(password, 12);

    // Kullanıcı oluştur (HR rolü ile)
    const [userResult] = await pool.execute(
      'INSERT INTO users (id, company_id, first_name, last_name, email, password_hash, role) VALUES (UUID(), ?, ?, ?, ?, ?, "hr")',
      [companyId, firstName, lastName, email, hashedPassword]
    );

    res.status(201).json({
      message: 'Kayıt başarılı. Şirket onayı bekleniyor.',
      companyId: companyId
    });
  } catch (error) {
    console.error('Register hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Me endpoint
router.get('/me', authenticateToken, async (req, res) => {
  try {
    const [users] = await pool.execute(
      'SELECT u.*, c.company_name FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.id = ?',
      [req.user.id]
    );

    if (users.length === 0) {
      return res.status(404).json({ message: 'Kullanıcı bulunamadı' });
    }

    const user = users[0];
    res.json({
      id: user.id,
      email: user.email,
      firstName: user.first_name,
      lastName: user.last_name,
      role: user.role,
      companyId: user.company_id,
      companyName: user.company_name,
      title: user.title,
      department: user.department,
      status: user.status
    });
  } catch (error) {
    console.error('Me endpoint hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Logout
router.post('/logout', authenticateToken, (req, res) => {
  res.json({ message: 'Çıkış başarılı' });
});

export default router;


