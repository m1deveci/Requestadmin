import express from 'express';
import bcrypt from 'bcryptjs';
import { body, validationResult } from 'express-validator';
import pool from '../config/database.js';
import { requireAdmin, requireHR } from '../middleware/auth.js';

const router = express.Router();

// Şirket kullanıcılarını getir
router.get('/', requireHR, async (req, res) => {
  try {
    let query = `
      SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role,
        u.title,
        u.department,
        u.status,
        u.last_login,
        u.created_at,
        l.location_name,
        p.province_name
      FROM users u
      LEFT JOIN locations l ON u.location_id = l.id
      LEFT JOIN provinces p ON u.province_id = p.id
    `;

    const params = [];

    // HR sadece kendi şirketinin kullanıcılarını görebilir
    if (req.user.role === 'hr') {
      query += ' WHERE u.company_id = ?';
      params.push(req.user.company_id);
    }

    query += ' ORDER BY u.created_at DESC';

    const [users] = await pool.execute(query, params);

    res.json(users);
  } catch (error) {
    console.error('Kullanıcıları getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Tek kullanıcı getir
router.get('/:id', requireHR, async (req, res) => {
  try {
    const { id } = req.params;

    const [users] = await pool.execute(`
      SELECT 
        u.*,
        c.company_name,
        l.location_name,
        p.province_name,
        m.first_name as manager_first_name,
        m.last_name as manager_last_name
      FROM users u
      LEFT JOIN companies c ON u.company_id = c.id
      LEFT JOIN locations l ON u.location_id = l.id
      LEFT JOIN provinces p ON u.province_id = p.id
      LEFT JOIN users m ON u.manager_id = m.id
      WHERE u.id = ?
    `, [id]);

    if (users.length === 0) {
      return res.status(404).json({ message: 'Kullanıcı bulunamadı' });
    }

    const user = users[0];

    // HR sadece kendi şirketinin kullanıcılarını görebilir
    if (req.user.role === 'hr' && user.company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu kullanıcıyı görme yetkiniz yok' });
    }

    // Şifre hash'ini kaldır
    delete user.password_hash;

    res.json(user);
  } catch (error) {
    console.error('Kullanıcı getirme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Yeni kullanıcı oluştur
router.post('/', [
  body('firstName').isLength({ min: 2, max: 100 }),
  body('lastName').isLength({ min: 2, max: 100 }),
  body('email').isEmail().normalizeEmail(),
  body('password').isLength({ min: 6 }),
  body('role').isIn(['hr', 'employee']),
  body('companyId').isUUID()
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz kullanıcı bilgileri',
        errors: errors.array()
      });
    }

    const {
      firstName,
      lastName,
      email,
      password,
      role,
      companyId,
      title,
      department,
      locationId,
      provinceId,
      managerId
    } = req.body;

    // Email kontrolü
    const [existingUsers] = await pool.execute(
      'SELECT id FROM users WHERE email = ?',
      [email]
    );

    if (existingUsers.length > 0) {
      return res.status(400).json({ message: 'Bu email adresi zaten kullanılıyor' });
    }

    // Şirket kontrolü
    const [companies] = await pool.execute(
      'SELECT id FROM companies WHERE id = ?',
      [companyId]
    );

    if (companies.length === 0) {
      return res.status(400).json({ message: 'Geçersiz şirket' });
    }

    // HR sadece kendi şirketine kullanıcı ekleyebilir
    if (req.user.role === 'hr' && companyId !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu şirkete kullanıcı ekleme yetkiniz yok' });
    }

    // Şifreyi hashle
    const hashedPassword = await bcrypt.hash(password, 12);

    const [result] = await pool.execute(`
      INSERT INTO users (
        id, company_id, first_name, last_name, email, password_hash, role,
        title, department, location_id, province_id, manager_id
      ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `, [
      companyId, firstName, lastName, email, hashedPassword, role,
      title || null, department || null, locationId || null, provinceId || null, managerId || null
    ]);

    res.status(201).json({
      message: 'Kullanıcı başarıyla oluşturuldu',
      userId: result.insertId
    });
  } catch (error) {
    console.error('Kullanıcı oluşturma hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Kullanıcı güncelle
router.put('/:id', [
  body('firstName').isLength({ min: 2, max: 100 }),
  body('lastName').isLength({ min: 2, max: 100 }),
  body('email').isEmail().normalizeEmail(),
  body('role').isIn(['hr', 'employee']),
  body('status').isIn(['active', 'inactive'])
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz kullanıcı bilgileri',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const {
      firstName,
      lastName,
      email,
      role,
      status,
      title,
      department,
      locationId,
      provinceId,
      managerId
    } = req.body;

    // Kullanıcı kontrolü
    const [users] = await pool.execute(
      'SELECT company_id FROM users WHERE id = ?',
      [id]
    );

    if (users.length === 0) {
      return res.status(404).json({ message: 'Kullanıcı bulunamadı' });
    }

    // HR sadece kendi şirketinin kullanıcılarını güncelleyebilir
    if (req.user.role === 'hr' && users[0].company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu kullanıcıyı güncelleme yetkiniz yok' });
    }

    // Email kontrolü
    const [existingUsers] = await pool.execute(
      'SELECT id FROM users WHERE email = ? AND id != ?',
      [email, id]
    );

    if (existingUsers.length > 0) {
      return res.status(400).json({ message: 'Bu email adresi zaten kullanılıyor' });
    }

    await pool.execute(`
      UPDATE users SET 
        first_name = ?, last_name = ?, email = ?, role = ?, status = ?,
        title = ?, department = ?, location_id = ?, province_id = ?, manager_id = ?,
        updated_at = NOW()
      WHERE id = ?
    `, [
      firstName, lastName, email, role, status,
      title || null, department || null, locationId || null, provinceId || null, managerId || null,
      id
    ]);

    res.json({ message: 'Kullanıcı başarıyla güncellendi' });
  } catch (error) {
    console.error('Kullanıcı güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

// Kullanıcı şifresini güncelle
router.patch('/:id/password', [
  body('newPassword').isLength({ min: 6 })
], requireHR, async (req, res) => {
  try {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ 
        message: 'Geçersiz şifre',
        errors: errors.array()
      });
    }

    const { id } = req.params;
    const { newPassword } = req.body;

    // Kullanıcı kontrolü
    const [users] = await pool.execute(
      'SELECT company_id FROM users WHERE id = ?',
      [id]
    );

    if (users.length === 0) {
      return res.status(404).json({ message: 'Kullanıcı bulunamadı' });
    }

    // HR sadece kendi şirketinin kullanıcılarının şifresini güncelleyebilir
    if (req.user.role === 'hr' && users[0].company_id !== req.user.company_id) {
      return res.status(403).json({ message: 'Bu kullanıcının şifresini güncelleme yetkiniz yok' });
    }

    // Şifreyi hashle
    const hashedPassword = await bcrypt.hash(newPassword, 12);

    await pool.execute(
      'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
      [hashedPassword, id]
    );

    res.json({ message: 'Şifre başarıyla güncellendi' });
  } catch (error) {
    console.error('Şifre güncelleme hatası:', error);
    res.status(500).json({ message: 'Sunucu hatası' });
  }
});

export default router;


